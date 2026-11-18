<?php

namespace Database\Seeders;

use App\Modules\Accounting\Services\ChartService;
use Illuminate\Database\Seeder;

/**
 * Bagan akun bawaan (Blueprint E.1 — kode selaras pemetaan Coretax)
 * untuk ember platform/instalasi mandiri (tenant_id NULL). Definisi
 * templat kanonis ada di ChartService (ISO-2) — seeder ini hanya
 * menanamnya ke ember NULL; salinan per tenant ditanam ChartService
 * saat tenant dibuat. Idempoten (updateOrCreate per kode per ember).
 */
class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        app(ChartService::class)->seedForBucket(null);
    }
}
