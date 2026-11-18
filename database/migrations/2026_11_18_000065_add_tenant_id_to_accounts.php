<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ISO-2 (audit isolasi menyeluruh 18 Nov 2026): bagan akun menjadi
 * milik per tenant. Sebelumnya accounts adalah rekaman bersama
 * se-instalasi (kode unik global, tanpa tenant_id) dan layanan
 * akun tidak memeriksa aktor — Owner satu tenant dapat mengganti
 * nama, membuat, dan menonaktifkan akun yang dipakai tenant lain.
 *
 * Skema mengikuti pola 000055 (period_locks): tenant_id nullable
 * (NULL = templat platform / ember instalasi mandiri) + tenant_key
 * COALESCE untuk keunikan per ember: unik (tenant_key, code).
 *
 * Isi ulang data: setiap tenant yang sudah ada menerima salinan
 * seluruh ember NULL, lalu journal_lines milik tenant itu dipetakan
 * ulang ke salinan akunnya sendiri (dicocokkan lewat kode). Jurnal
 * ember NULL tidak berubah. Tenant baru sesudah migrasi ini
 * menerima salinannya lewat ChartService (kaitan Tenant::created).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // cascadeOnDelete (bukan restrict seperti jurnal): bagan
            // adalah konfigurasi milik tenant yang ditanam otomatis
            // saat tenant lahir — tenant tanpa data bisnis harus tetap
            // dapat dihapus beserta bagannya. Tenant BERJURNAL tetap
            // tertahan FK restrict milik journals/journal_lines.
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            // VIRTUAL (bukan STORED): InnoDB menolak kolom generated
            // STORED di atas kolom FK beraksi CASCADE (error 1215) —
            // pola yang sama dengan migrasi 000051.
            $table->unsignedBigInteger('tenant_key')
                ->virtualAs('COALESCE(tenant_id, 0)');
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['tenant_key', 'code']);
        });

        $this->backfillTenantCharts();
    }

    public function down(): void
    {
        // Petakan ulang baris jurnal tenant ke akun ember NULL
        // (dicocokkan lewat kode), lalu buang salinan per tenant.
        $templateByCode = DB::table('accounts')->whereNull('tenant_id')
            ->pluck('id', 'code');
        $tenantAccounts = DB::table('accounts')->whereNotNull('tenant_id')->get();
        foreach ($tenantAccounts as $account) {
            $templateId = $templateByCode[$account->code] ?? null;
            if ($templateId !== null) {
                DB::table('journal_lines')->where('account_id', $account->id)
                    ->update(['account_id' => $templateId]);
            }
        }
        DB::table('accounts')->whereNotNull('tenant_id')->delete();

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['tenant_key', 'code']);
            $table->unique('code');
        });
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('tenant_key');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }

    private function backfillTenantCharts(): void
    {
        $template = DB::table('accounts')->whereNull('tenant_id')
            ->orderBy('id')->get();
        if ($template->isEmpty()) {
            return;
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            // Lewati tenant yang sudah memiliki bagan sendiri.
            $existing = DB::table('accounts')->where('tenant_id', $tenantId)
                ->pluck('code');
            if ($existing->isNotEmpty()) {
                continue;
            }

            $idByCode = [];
            foreach ($template as $row) {
                $idByCode[$row->code] = DB::table('accounts')->insertGetId([
                    'tenant_id' => $tenantId,
                    'code' => $row->code,
                    'name' => $row->name,
                    'group' => $row->group,
                    'parent_id' => null,
                    'branch_id' => $row->branch_id,
                    'is_active' => $row->is_active,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
            // Lulus kedua: petakan induk di dalam salinan tenant.
            foreach ($template as $row) {
                if ($row->parent_id === null) {
                    continue;
                }
                $parentCode = $template->firstWhere('id', $row->parent_id)?->code;
                if ($parentCode !== null && isset($idByCode[$parentCode])) {
                    DB::table('accounts')->where('id', $idByCode[$row->code])
                        ->update(['parent_id' => $idByCode[$parentCode]]);
                }
            }

            // Petakan ulang baris jurnal tenant ke salinan akunnya.
            $oldIdsByCode = $template->pluck('id', 'code');
            foreach ($idByCode as $code => $newId) {
                DB::table('journal_lines')
                    ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
                    ->where('journals.tenant_id', $tenantId)
                    ->where('journal_lines.account_id', $oldIdsByCode[$code])
                    ->update(['journal_lines.account_id' => $newId]);
            }
        }
    }
};
