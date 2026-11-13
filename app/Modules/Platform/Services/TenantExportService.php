<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Ekspor data SATU tenant (B-03): arsip ZIP berisi backup.json
 * dengan HANYA baris milik tenant itu — pendamping per-tenant
 * dari BackupService (yang snapshot-nya seluruh instalasi dan
 * khusus platform).
 *
 * Klasifikasi tabel EKSPLISIT dan gagal-tertutup: sebuah tabel
 * hanya masuk ekspor bila tercatat di registry di bawah; tabel
 * yang tidak dikenal TIDAK pernah disalin utuh. Registry wajib
 * lengkap — test kelengkapan membandingkan registry ini dengan
 * skema nyata, sehingga migrasi yang menambah tabel baru akan
 * menggagalkan test sampai tabel itu diklasifikasikan.
 *
 * Kelas partisi:
 * - TENANT_TABLES: saring tenant_id = tenant.
 * - BRANCH_TABLES: saring branch_id ∈ cabang tenant.
 * - FROM_BRANCH_TABLES: saring from_branch_id ∈ cabang tenant.
 * - CHILD_TABLES: saring kolom FK ∈ id baris induk yang sudah
 *   terekstrak (rantai anak: item pembayaran dsb).
 * - TENANT_STAMPED_TABLES: saring cap tenant_id = tenant, DITAMBAH
 *   baris warisan tanpa cap yang cabangnya milik tenant (khusus
 *   audit_logs pasca L-01: log tingkat perusahaan bercap tenant
 *   dengan branch_id NULL wajib ikut; log platform/sistem tanpa
 *   cap dan tanpa cabang tidak pernah ikut).
 * - SELF_TABLES: baris identitas tenant itu sendiri.
 * - EXCLUDED_TABLES: kredensial, operasional antrean/sesi, dan
 *   data global instalasi — tidak pernah masuk ekspor tenant.
 *
 * Kolom kredensial pada tabel yang disertakan dibuang
 * (STRIP_COLUMNS): hash kata sandi, token perangkat, dsb tidak
 * boleh meninggalkan sistem di dalam berkas unduhan.
 *
 * Representasi role (keputusan audit putaran 1): role khusus
 * tenant + role templat global (tenant_id NULL) yang dirujuk
 * pengguna tenant — agar user_role tidak menggantung; role
 * khusus tenant lain tidak pernah ikut.
 *
 * Ekspor ini BUKAN berkas restore: memulihkan satu tenant ke
 * instalasi berjalan tidak didukung (restore tetap whole-DB
 * tingkat platform lewat BackupService).
 */
class TenantExportService
{
    /** @var list<string> */
    private const TENANT_TABLES = [
        'branches', 'commissions', 'customers', 'doctors', 'fixed_assets',
        'incentive_rules', 'insurances', 'journals', 'membership_levels',
        'payrolls', 'period_locks', 'prescriptions', 'product_parents',
        'products', 'roles', 'settings', 'suppliers', 'tenant_billing_requests',
        'tenant_feature_overrides', 'tenant_subscriptions', 'users',
    ];

    /** @var list<string> */
    private const BRANCH_TABLES = [
        'accounts', 'broken_stocks', 'customer_credits',
        'fitting_measurements', 'incentive_periods', 'insurance_usages',
        'journal_lines',
        'offline_devices', 'offline_exceptions', 'purchase_orders',
        'purchase_requests', 'sale_returns', 'sales', 'stock_layers',
        'stock_movements', 'stock_opnames', 'user_branch', 'warranty_cases',
    ];

    /** @var list<string> */
    private const FROM_BRANCH_TABLES = ['stock_transfers'];

    /** @var list<string> */
    private const TENANT_STAMPED_TABLES = ['audit_logs'];

    /** @var array<string, array{0: string, 1: string}> tabel anak => [tabel induk, kolom FK] */
    private const CHILD_TABLES = [
        'sale_items' => ['sales', 'sale_id'],
        'sale_payments' => ['sales', 'sale_id'],
        'sale_installments' => ['sales', 'sale_id'],
        'lens_processes' => ['sales', 'sale_id'],
        'qc_checks' => ['lens_processes', 'lens_process_id'],
        'purchase_order_items' => ['purchase_orders', 'purchase_order_id'],
        'receipts' => ['purchase_orders', 'purchase_order_id'],
        'receipt_items' => ['receipts', 'receipt_id'],
        'purchase_returns' => ['receipts', 'receipt_id'],
        'purchase_return_items' => ['purchase_returns', 'purchase_return_id'],
        'purchase_request_items' => ['purchase_requests', 'purchase_request_id'],
        'purchase_payments' => ['suppliers', 'supplier_id'],
        'purchase_payment_allocations' => ['purchase_payments', 'purchase_payment_id'],
        'sale_return_items' => ['sale_returns', 'sale_return_id'],
        'stock_transfer_items' => ['stock_transfers', 'stock_transfer_id'],
        'stock_opname_items' => ['stock_opnames', 'stock_opname_id'],
        'stock_layer_consumptions' => ['stock_layers', 'layer_id'],
        'warranty_payments' => ['warranty_cases', 'warranty_case_id'],
        'warranty_replacements' => ['warranty_cases', 'warranty_case_id'],
        'customer_credit_applications' => ['customer_credits', 'customer_credit_id'],
        'points_ledger' => ['customers', 'customer_id'],
        'product_attributes' => ['products', 'product_id'],
        'wa_messages' => ['customers', 'customer_id'],
        'incentive_items' => ['incentive_periods', 'incentive_period_id'],
        'incentive_adjustments' => ['incentive_periods', 'incentive_period_id'],
        'role_permission' => ['roles', 'role_id'],
        'user_role' => ['users', 'user_id'],
    ];

    /** @var list<string> */
    private const SELF_TABLES = ['tenants'];

    /** @var list<string> */
    private const EXCLUDED_TABLES = [
        // Kredensial & token (keputusan desain B-03: tidak keluar sistem).
        'api_tokens', 'member_portal_tokens', 'password_reset_tokens',
        // Operasional: sesi, cache, antrean — bukan data bisnis tenant.
        'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'migrations',
        // Data global instalasi / platform.
        'plans', 'plan_features', 'permissions', 'product_categories',
        'qc_point_templates', 'document_counters',
        // Konfigurasi tingkat instalasi berkredensial terenkripsi
        // dan konten WA tingkat instalasi (OD-4 belum diputuskan).
        'ai_settings', 'wa_gateways', 'wa_campaigns', 'wa_templates',
    ];

    /** @var array<string, list<string>> */
    private const STRIP_COLUMNS = [
        'users' => ['password', 'remember_token'],
        'offline_devices' => ['token_hash'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** Nama-nama tabel yang diatur registry ini (untuk test kelengkapan). */
    public static function classifiedTables(): array
    {
        return [
            ...self::TENANT_TABLES,
            ...self::BRANCH_TABLES,
            ...self::FROM_BRANCH_TABLES,
            ...self::TENANT_STAMPED_TABLES,
            ...array_keys(self::CHILD_TABLES),
            ...self::SELF_TABLES,
            ...self::EXCLUDED_TABLES,
        ];
    }

    public function directory(Tenant $tenant): string
    {
        $dir = storage_path('app/backups/tenant-exports/'.$tenant->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** @return array<int, array{name: string, size: int, created_at: int, verified: bool}> */
    public function entries(Tenant $tenant): array
    {
        $entries = [];
        foreach (glob($this->directory($tenant).'/*.zip') ?: [] as $file) {
            $entries[] = [
                'name' => basename($file),
                'size' => (int) filesize($file),
                'created_at' => (int) filemtime($file),
                'verified' => $this->verify($tenant, basename($file)),
            ];
        }
        usort($entries, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $entries;
    }

    /** Arsip harus terbaca, bertanda ekspor tenant ini. */
    public function verify(Tenant $tenant, string $name): bool
    {
        $path = $this->path($tenant, $name);
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
            && ($data['kind'] ?? null) === 'tenant_export'
            && (int) ($data['tenant_id'] ?? 0) === (int) $tenant->id
            && isset($data['tables']) && is_array($data['tables']);
    }

    public function path(Tenant $tenant, string $name): ?string
    {
        $path = $this->directory($tenant).'/'.basename($name);

        return is_file($path) ? $path : null;
    }

    /** @return array<string, mixed> muatan ekspor (dipakai layanan + test) */
    public function buildPayload(Tenant $tenant, ?User $actor): array
    {
        $database = (string) DB::connection()->getDatabaseName();
        $liveTables = [];
        foreach (Schema::getTables() as $meta) {
            if (($meta['schema'] ?? $database) === $database) {
                $liveTables[] = (string) $meta['name'];
            }
        }

        $branchIds = DB::table('branches')->where('tenant_id', $tenant->id)->pluck('id')
            ->map(fn ($id) => (int) $id)->all();

        $tables = [];
        $idSets = [];
        $extract = function (string $table, $query) use (&$tables, &$idSets) {
            $rows = $query->get()->map(fn ($row) => (array) $row)->all();
            foreach (self::STRIP_COLUMNS[$table] ?? [] as $column) {
                $rows = array_map(static function (array $row) use ($column) {
                    unset($row[$column]);

                    return $row;
                }, $rows);
            }
            $tables[$table] = $rows;
            $idSets[$table] = array_map(static fn (array $row) => (int) $row['id'], array_filter($rows, static fn (array $row) => isset($row['id'])));
        };

        // Urutan penting: induk diekstrak sebelum anak (CHILD_TABLES
        // diurutkan mengikuti rantai ketergantungannya).
        foreach (self::SELF_TABLES as $table) {
            if (in_array($table, $liveTables, true)) {
                $extract($table, DB::table($table)->where('id', $tenant->id));
            }
        }
        foreach (self::TENANT_TABLES as $table) {
            // 'roles' diekstrak pada langkah khusus sesudah rantai
            // anak (lihat di bawah) — bukan di putaran umum ini.
            if ($table === 'roles') {
                continue;
            }
            if (in_array($table, $liveTables, true)) {
                $extract($table, DB::table($table)->where('tenant_id', $tenant->id));
            }
        }
        foreach (self::BRANCH_TABLES as $table) {
            if (in_array($table, $liveTables, true)) {
                $extract($table, DB::table($table)->whereIn('branch_id', $branchIds));
            }
        }
        foreach (self::FROM_BRANCH_TABLES as $table) {
            if (in_array($table, $liveTables, true)) {
                $extract($table, DB::table($table)->whereIn('from_branch_id', $branchIds));
            }
        }
        foreach (self::TENANT_STAMPED_TABLES as $table) {
            if (in_array($table, $liveTables, true)) {
                $extract($table, DB::table($table)->where(function ($query) use ($tenant, $branchIds) {
                    $query->where('tenant_id', $tenant->id)
                        ->orWhere(function ($inner) use ($branchIds) {
                            $inner->whereNull('tenant_id')->whereIn('branch_id', $branchIds);
                        });
                }));
            }
        }
        foreach (self::CHILD_TABLES as $table => [$parent, $fk]) {
            // 'role_permission' mengikuti langkah khusus roles.
            if ($table === 'role_permission') {
                continue;
            }
            if (in_array($table, $liveTables, true)) {
                $extract($table, DB::table($table)->whereIn($fk, $idSets[$parent] ?? []));
            }
        }

        // Langkah khusus ROLES (keputusan desain audit putaran 1):
        // pengguna tenant dirujuk ke role khusus tenant DAN role
        // templat global (tenant_id NULL — misalnya Owner bawaan
        // yang dipasangkan TenantSignupService). Agar ekspor tidak
        // memuat relasi user_role yang menggantung, role global
        // yang BENAR-BENAR dirujuk pengguna tenant ini ikut
        // disertakan (templat bersama tingkat instalasi, bukan data
        // privat tenant lain); role khusus tenant lain tidak pernah
        // ikut. role_permission mengikuti himpunan role final ini.
        if (in_array('roles', $liveTables, true)) {
            $referencedRoleIds = array_map(
                static fn (array $row) => (int) $row['role_id'],
                $tables['user_role'] ?? [],
            );
            $extract('roles', DB::table('roles')->where(function ($query) use ($tenant, $referencedRoleIds) {
                $query->where('tenant_id', $tenant->id)
                    ->orWhere(function ($inner) use ($referencedRoleIds) {
                        $inner->whereNull('tenant_id')->whereIn('id', $referencedRoleIds);
                    });
            }));
            if (in_array('role_permission', $liveTables, true)) {
                $extract('role_permission', DB::table('role_permission')->whereIn('role_id', $idSets['roles'] ?? []));
            }
        }

        return [
            'app' => 'lentera',
            'kind' => 'tenant_export',
            'tenant_id' => (int) $tenant->id,
            'tenant_name' => $tenant->name,
            'created_at' => now()->toIso8601String(),
            'created_by' => $actor?->username ?? 'system',
            'tables' => $tables,
        ];
    }

    public function create(User $actor, Tenant $tenant): string
    {
        $data = $this->buildPayload($tenant, $actor);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw ValidationException::withMessages(['backup' => __('ui.backups.encode_failed')]);
        }

        $name = 'tenant-'.$tenant->id.'-'.now()->format('Ymd-His').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($this->directory($tenant).'/'.$name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages(['backup' => __('ui.backups.write_failed')]);
        }
        $zip->addFromString('backup.json', $payload);
        $zip->close();

        $this->audit->log(
            action: 'tenant_export.created',
            subjectType: 'Tenant',
            subjectId: (int) $tenant->id,
            newValues: ['file' => $name, 'tables' => count($data['tables'])],
            actor: $actor,
        );

        return $name;
    }

    public function delete(User $actor, Tenant $tenant, string $name): void
    {
        $path = $this->path($tenant, $name);
        if ($path === null) {
            throw ValidationException::withMessages(['berkas' => __('ui.backups.not_found')]);
        }
        unlink($path);
        $this->audit->log(
            action: 'tenant_export.deleted',
            subjectType: 'Tenant',
            subjectId: (int) $tenant->id,
            newValues: ['file' => basename($name)],
            actor: $actor,
        );
    }
}
