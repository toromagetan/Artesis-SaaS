<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Backup & restore basis data di dalam aplikasi (paritas
 * Artesis). Format: satu berkas ZIP berisi backup.json —
 * snapshot DATA semua tabel (skema tetap milik migrasi).
 * Restore: backup keselamatan dibuat dulu, lalu setiap tabel
 * dikosongkan & diisi ulang dari snapshot dalam satu transaksi
 * (FK checks nonaktif selama pengisian), dan jejak audit
 * ditulis SESUDAH restore agar tidak ikut terhapus.
 */
class BackupService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function directory(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** @return array<int, array{name: string, size: int, created_at: int, verified: bool}> */
    public function entries(): array
    {
        $entries = [];
        foreach (glob($this->directory().'/*.zip') ?: [] as $file) {
            $entries[] = [
                'name' => basename($file),
                'size' => (int) filesize($file),
                'created_at' => (int) filemtime($file),
                'verified' => $this->verify(basename($file)),
            ];
        }
        usort($entries, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $entries;
    }

    /**
     * Verifikasi keterbacaan backup (PRD-Detail 12.6): arsip zip
     * harus terbuka dan backup.json di dalamnya harus terbaca,
     * bertanda aplikasi ini, dan memuat tabel. Backup dianggap
     * berhasil hanya bila lolos verifikasi ini.
     */
    public function verify(string $name): bool
    {
        $path = $this->path($name);
        if ($path === null) {
            return false;
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }
        $raw = $zip->getFromName('backup.json');
        $zip->close();
        if ($raw === false) {
            return false;
        }
        $data = json_decode($raw, true);

        return is_array($data)
            && ($data['app'] ?? null) === 'lentera'
            && isset($data['tables'])
            && is_array($data['tables'])
            && $data['tables'] !== [];
    }

    /**
     * Kesehatan operasional untuk peringatan dashboard:
     * backup terakhir + jumlah job antrean yang gagal.
     *
     * @return array{latest_at: int|null, latest_verified: bool, failed_jobs: int}
     */
    public function health(): array
    {
        $entries = $this->entries();
        $latest = $entries[0] ?? null;

        return [
            'latest_at' => $latest['created_at'] ?? null,
            'latest_verified' => $latest['verified'] ?? false,
            'failed_jobs' => Schema::hasTable('failed_jobs')
                ? (int) DB::table('failed_jobs')->count()
                : 0,
        ];
    }

    /**
     * Backup terjadwal oleh sistem (audit 23 Okt 2026): aktor
     * dicatat sebagai 'system' dan pembuatan teraudit tanpa aktor
     * pengguna. Retensi hanya menyentuh berkas awalan 'auto'.
     */
    public function createSystem(string $prefix = 'auto'): string
    {
        return $this->createInternal(null, $prefix);
    }

    /** Hapus backup otomatis tertua melebihi jumlah simpan; kembalikan jumlah terhapus. */
    public function pruneAuto(int $keep = 14): int
    {
        $autos = array_values(array_filter(
            $this->entries(),
            fn (array $e) => str_starts_with($e['name'], 'auto-'),
        ));
        $deleted = 0;
        foreach (array_slice($autos, $keep) as $entry) {
            $path = $this->path($entry['name']);
            if ($path !== null && is_file($path)) {
                unlink($path);
                $deleted++;
            }
        }
        if ($deleted > 0) {
            $this->audit->log(
                action: 'backup.pruned',
                subjectType: 'Backup',
                subjectId: null,
                newValues: ['deleted' => $deleted, 'keep' => $keep],
            );
        }

        return $deleted;
    }

    public function create(User $actor, string $prefix = 'backup'): string
    {
        return $this->createInternal($actor, $prefix);
    }

    private function createInternal(?User $actor, string $prefix): string
    {
        // HANYA tabel basis data koneksi saat ini: getTableListing()
        // tanpa saringan mengembalikan tabel TERKUALIFIKASI dari
        // SEMUA basis data yang dapat dilihat akun MySQL (pada akun
        // multi-basis data, backup akan menyalin — dan restore akan
        // menimpa — basis data LAIN). Tersaring ke skema berjalan.
        $database = (string) DB::connection()->getDatabaseName();
        $tables = [];
        foreach (Schema::getTables() as $meta) {
            if (($meta['schema'] ?? $database) !== $database) {
                continue;
            }
            $table = (string) $meta['name'];
            $tables[$table] = DB::table($table)->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }
        $payload = json_encode([
            'app' => 'lentera',
            'created_at' => now()->toIso8601String(),
            'created_by' => $actor?->username ?? 'system',
            'tables' => $tables,
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw ValidationException::withMessages(['backup' => __('ui.backups.encode_failed')]);
        }

        $name = $prefix.'-'.now()->format('Ymd-His').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($this->directory().'/'.$name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['backup' => __('ui.backups.write_failed')]);
        }
        $zip->addFromString('backup.json', $payload);
        $zip->close();

        $this->audit->log(
            action: $actor === null ? 'backup.auto_created' : 'backup.created',
            subjectType: 'Backup',
            subjectId: null,
            newValues: ['file' => $name, 'tables' => count($tables)],
            actor: $actor,
        );

        return $name;
    }

    public function path(string $name): ?string
    {
        $safe = basename($name);
        $path = $this->directory().'/'.$safe;

        return is_file($path) ? $path : null;
    }

    public function delete(User $actor, string $name): void
    {
        $path = $this->path($name);
        if ($path === null) {
            throw ValidationException::withMessages(['berkas' => __('ui.backups.not_found')]);
        }
        unlink($path);
        $this->audit->log(action: 'backup.deleted', subjectType: 'Backup', newValues: ['file' => basename($name)], actor: $actor);
    }

    /** @return list<string> */
    private function generatedColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND EXTRA LIKE '%GENERATED%'",
            [(string) DB::connection()->getDatabaseName(), $table],
        );

        return array_map(static fn ($row) => (string) $row->name, $rows);
    }

    /** @return array{restored_tables: int, safety_backup: string} */
    public function restore(User $actor, string $name): array
    {
        $path = $this->path($name);
        if ($path === null) {
            throw ValidationException::withMessages(['berkas' => __('ui.backups.not_found')]);
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['berkas' => __('ui.backups.unreadable')]);
        }
        $json = $zip->getFromName('backup.json');
        $zip->close();
        $payload = $json === false ? null : json_decode($json, true);
        if (! is_array($payload) || ($payload['app'] ?? null) !== 'lentera' || ! isset($payload['tables'])) {
            throw ValidationException::withMessages(['berkas' => __('ui.backups.invalid')]);
        }

        // Backup keselamatan atas keadaan saat ini SEBELUM menimpa.
        $safety = $this->create($actor, 'pra-restore');

        $restored = 0;
        DB::transaction(function () use ($payload, &$restored) {
            Schema::disableForeignKeyConstraints();
            foreach ($payload['tables'] as $table => $rows) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                // Kolom TER-GENERATE (mis. points_ledger.earn_sale_key)
                // tidak boleh disisipkan eksplisit (MySQL 3105) —
                // nilainya dihitung ulang server dari kolom sumber.
                $generated = $this->generatedColumns((string) $table);
                if ($generated !== []) {
                    $rows = array_map(static function (array $row) use ($generated) {
                        foreach ($generated as $column) {
                            unset($row[$column]);
                        }

                        return $row;
                    }, $rows);
                }
                DB::table($table)->delete();
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                $restored++;
            }
            Schema::enableForeignKeyConstraints();
        });

        // Audit ditulis sesudah restore (baris audit lama ikut
        // tergantikan snapshot — jejak restore harus bertahan).
        $this->audit->log(
            action: 'backup.restored',
            subjectType: 'Backup',
            newValues: ['file' => basename($name), 'tables' => $restored, 'safety_backup' => $safety],
            actor: $actor,
        );

        return ['restored_tables' => $restored, 'safety_backup' => $safety];
    }
}
