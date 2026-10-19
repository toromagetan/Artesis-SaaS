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

    /** @return array<int, array{name: string, size: int, created_at: int}> */
    public function entries(): array
    {
        $entries = [];
        foreach (glob($this->directory().'/*.zip') ?: [] as $file) {
            $entries[] = [
                'name' => basename($file),
                'size' => (int) filesize($file),
                'created_at' => (int) filemtime($file),
            ];
        }
        usort($entries, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $entries;
    }

    public function create(User $actor, string $prefix = 'backup'): string
    {
        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            $tables[$table] = DB::table($table)->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        }
        $payload = json_encode([
            'app' => 'lentera',
            'created_at' => now()->toIso8601String(),
            'created_by' => $actor->username,
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
            action: 'backup.created',
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
