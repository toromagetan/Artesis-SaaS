<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Registry fitur & paket SaaS kanonis (M3, Rencana Fase 1 Artesis SaaS).
 *
 * Kunci fitur di luar registry = bug: kode yang memeriksa/menanam fitur
 * WAJIB lewat kelas ini (pola yang sama dengan PermissionRegistry).
 * Definisi paket bawaan di sini adalah SUMBER untuk penanaman awal
 * (SaasSeeder) — sesudah ditanam, panel platform adalah sumber
 * kebenaran operasional (owner platform dapat mengubahnya).
 *
 * Tangga harga: ketetapan CEO 2026-10-29 (di bawah Lensiro, strategi
 * penetrasi). Label fitur untuk UI ada di lang ui.plans.features.*.
 */
class PlanFeatureRegistry
{
    /** @return array<string, array{kind: string}> kunci fitur => jenis */
    public static function features(): array
    {
        return [
            // Fitur boolean (kunci/buka).
            'reports_advanced' => ['kind' => 'boolean'],   // laporan penuh + ekspor
            'roles_permissions' => ['kind' => 'boolean'],  // peran & izin kustom
            'crm_members' => ['kind' => 'boolean'],        // member, poin, portal
            'purchasing_full' => ['kind' => 'boolean'],    // PR/PO penuh
            'accounting_full' => ['kind' => 'boolean'],    // akuntansi double-entry penuh
            'whatsapp' => ['kind' => 'boolean'],           // gateway WA, kampanye, inbox
            'api_access' => ['kind' => 'boolean'],         // token API baca
            'ai_assistant' => ['kind' => 'boolean'],       // asisten AI bisnis
            // Batas angka (null = tanpa batas).
            'products_max' => ['kind' => 'limit'],
            'users_max' => ['kind' => 'limit'],
            'branches_max' => ['kind' => 'limit'],
            'report_history_days' => ['kind' => 'limit'],
        ];
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::features());
    }

    public static function exists(string $featureKey): bool
    {
        return isset(self::features()[$featureKey]);
    }

    public static function assertExists(string $featureKey): void
    {
        if (! self::exists($featureKey)) {
            throw new InvalidArgumentException("Fitur tidak terdaftar di registry: {$featureKey}");
        }
    }

    public static function kind(string $featureKey): string
    {
        self::assertExists($featureKey);

        return self::features()[$featureKey]['kind'];
    }

    /**
     * Definisi paket bawaan (penanaman awal). Nilai fitur boolean 0/1;
     * batas angka null = tanpa batas.
     *
     * @return array<string, array{name: string, price_monthly: int, extra_branch_price: int, sort_order: int, features: array<string, int|null>}>
     */
    public static function defaultPlans(): array
    {
        $off = [
            'reports_advanced' => 0, 'roles_permissions' => 0, 'crm_members' => 0,
            'purchasing_full' => 0, 'accounting_full' => 0, 'whatsapp' => 0,
            'api_access' => 0, 'ai_assistant' => 0,
        ];

        return [
            'free' => [
                'name' => 'Free',
                'price_monthly' => 0,
                'extra_branch_price' => 0,
                'sort_order' => 1,
                'features' => array_merge($off, [
                    'products_max' => 500, 'users_max' => 1,
                    'branches_max' => 1, 'report_history_days' => 90,
                ]),
            ],
            'solo' => [
                'name' => 'Solo',
                'price_monthly' => 49000,
                'extra_branch_price' => 0,
                'sort_order' => 2,
                'features' => array_merge($off, [
                    'reports_advanced' => 1,
                    'products_max' => null, 'users_max' => 2,
                    'branches_max' => 1, 'report_history_days' => null,
                ]),
            ],
            'team' => [
                'name' => 'Team',
                'price_monthly' => 129000,
                'extra_branch_price' => 0,
                'sort_order' => 3,
                'features' => array_merge($off, [
                    'reports_advanced' => 1, 'roles_permissions' => 1,
                    'crm_members' => 1, 'purchasing_full' => 1, 'ai_assistant' => 1,
                    'products_max' => null, 'users_max' => 7,
                    'branches_max' => 1, 'report_history_days' => null,
                ]),
            ],
            'best_offer' => [
                'name' => 'Best Offer',
                'price_monthly' => 199000,
                'extra_branch_price' => 99000,
                'sort_order' => 4,
                'features' => array_merge($off, [
                    'reports_advanced' => 1, 'roles_permissions' => 1,
                    'crm_members' => 1, 'purchasing_full' => 1,
                    'accounting_full' => 1, 'whatsapp' => 1,
                    'api_access' => 1, 'ai_assistant' => 1,
                    'products_max' => null, 'users_max' => null,
                    'branches_max' => null, 'report_history_days' => null,
                ]),
            ],
        ];
    }
}
