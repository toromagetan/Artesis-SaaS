<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\BackupService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * M5 (audit 13 Nov 2026, temuan A-03) — penguatan verifikasi
 * backup. verify() lama hanya membuktikan arsip TERBUKA dan
 * backup.json terbaca; kerusakan isi senyap lolos. Kini backup
 * baru membawa manifest (jumlah baris per tabel + sha256 muatan
 * tabel kanonis) yang wajib cocok. Backup warisan tanpa manifest
 * tetap lolos pada tingkat keterbacaan lama — dibuktikan test.
 */
class BackupVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->service = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->service->entries() as $entry) {
            @unlink($this->service->directory().'/'.$entry['name']);
        }
        parent::tearDown();
    }

    /** Baca backup.json dari arsip; kembalikan [data, jalur]. */
    private function bacaPayload(string $name): array
    {
        $path = $this->service->directory().'/'.$name;
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::RDONLY));
        $raw = $zip->getFromName('backup.json');
        $zip->close();
        $this->assertIsString($raw);

        return [json_decode($raw, true), $path];
    }

    /** Tulis ulang arsip dengan payload termodifikasi. */
    private function tulisUlang(string $path, array $data): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('backup.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        $zip->close();
    }

    public function test_backup_baru_membawa_manifest_dan_terverifikasi(): void
    {
        $name = $this->service->create($this->owner);
        [$data] = $this->bacaPayload($name);
        $this->assertArrayHasKey('manifest', $data);
        $this->assertSame(count($data['tables']['users']), $data['manifest']['rows']['users']);
        $canonical = json_encode($data['tables'], JSON_UNESCAPED_UNICODE);
        $this->assertSame(hash('sha256', $canonical), $data['manifest']['sha256']);
        $this->assertTrue($this->service->verify($name));
    }

    public function test_baris_hilang_dari_isi_terdeteksi(): void
    {
        $name = $this->service->create($this->owner);
        [$data, $path] = $this->bacaPayload($name);
        array_pop($data['tables']['users']);
        $this->tulisUlang($path, $data);
        $this->assertFalse($this->service->verify($name));
    }

    public function test_isi_diubah_tanpa_mengubah_jumlah_terdeteksi(): void
    {
        $name = $this->service->create($this->owner);
        [$data, $path] = $this->bacaPayload($name);
        $data['tables']['users'][0]['name'] = 'Diubah Diam-diam';
        $this->tulisUlang($path, $data);
        $this->assertFalse($this->service->verify($name));
    }

    public function test_manifest_palsu_terdeteksi(): void
    {
        $name = $this->service->create($this->owner);
        [$data, $path] = $this->bacaPayload($name);
        $data['manifest']['rows']['users'] = 999999;
        $this->tulisUlang($path, $data);
        $this->assertFalse($this->service->verify($name));
    }

    public function test_backup_warisan_tanpa_manifest_lolos_tingkat_keterbacaan(): void
    {
        $name = $this->service->create($this->owner);
        [$data, $path] = $this->bacaPayload($name);
        unset($data['manifest']);
        $this->tulisUlang($path, $data);
        $this->assertTrue($this->service->verify($name));
    }
}
