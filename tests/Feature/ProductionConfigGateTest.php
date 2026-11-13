<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * M3 (audit 13 Nov 2026, temuan A-01/A-02) — gerbang konfigurasi
 * produksi `lentera:cek-produksi`. Kegagalan senyap yang dicegah:
 * verifikasi email B-01 tidak terkirim karena mailer masih 'log',
 * dan anti-bot Turnstile mati karena kunci kosong. Perintah ini
 * read-only: hanya membaca config aktif.
 */
class ProductionConfigGateTest extends TestCase
{
    /** Konfigurasi produksi yang sah penuh; tiap test merusaknya seperlunya. */
    private function konfigurasiSah(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'pemilik@gmail.com',
            'mail.mailers.smtp.password' => 'app-password-uji',
            'mail.from.address' => 'pemilik@gmail.com',
            'services.turnstile.site_key' => 'site-key-uji',
            'services.turnstile.secret' => 'secret-uji',
            'app.debug' => false,
            'app.url' => 'https://istori.my.id',
        ]);
    }

    public function test_lulus_saat_konfigurasi_produksi_lengkap(): void
    {
        $this->konfigurasiSah();
        $this->artisan('lentera:cek-produksi')
            ->expectsOutputToContain('GERBANG PRODUKSI LULUS')
            ->assertExitCode(0);
    }

    public function test_gagal_saat_mailer_masih_log(): void
    {
        $this->konfigurasiSah();
        config(['mail.default' => 'log']);
        $this->artisan('lentera:cek-produksi')
            ->expectsOutputToContain('GERBANG PRODUKSI GAGAL')
            ->assertExitCode(1);
    }

    public function test_gagal_saat_alamat_pengirim_masih_contoh(): void
    {
        $this->konfigurasiSah();
        config(['mail.from.address' => 'hello@example.com']);
        $this->artisan('lentera:cek-produksi')->assertExitCode(1);
    }

    public function test_gagal_saat_kredensial_smtp_kurang(): void
    {
        $this->konfigurasiSah();
        config(['mail.mailers.smtp.password' => null]);
        $this->artisan('lentera:cek-produksi')->assertExitCode(1);
    }

    public function test_gagal_saat_kunci_turnstile_kosong_atau_separuh(): void
    {
        $this->konfigurasiSah();
        config(['services.turnstile.site_key' => null, 'services.turnstile.secret' => null]);
        $this->artisan('lentera:cek-produksi')->assertExitCode(1);

        $this->konfigurasiSah();
        config(['services.turnstile.secret' => null]);
        $this->artisan('lentera:cek-produksi')->assertExitCode(1);
    }

    public function test_gagal_saat_debug_atau_url_tidak_produksi(): void
    {
        $this->konfigurasiSah();
        config(['app.debug' => true]);
        $this->artisan('lentera:cek-produksi')->assertExitCode(1);

        $this->konfigurasiSah();
        config(['app.url' => 'http://istori.my.id']);
        $this->artisan('lentera:cek-produksi')->assertExitCode(1);
    }
}
