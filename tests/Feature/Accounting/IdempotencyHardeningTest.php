<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Membership\Models\PointsLedger;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengerasan idempotensi DB (audit 23 Okt 2026 C5; migrasi 000044):
 * postOnce ditegakkan indeks unik idempotency_key TANPA mengunci
 * jalur sah lain (penyusutan multi-periode, jurnal manual), dan
 * baris earn poin per penjualan unik di basis data sementara
 * redeem berkali-kali tetap sah.
 */
class IdempotencyHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private PostingService $posting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->posting = app(PostingService::class);
    }

    private function lines(): array
    {
        return [
            ['account' => '1-1100', 'branch_id' => $this->branch->id, 'debit' => '1000', 'credit' => '0'],
            ['account' => '4-1100', 'branch_id' => $this->branch->id, 'debit' => '0', 'credit' => '1000'],
        ];
    }

    public function test_post_once_is_unique_at_database_level(): void
    {
        $first = $this->posting->postOnce('sale', 42, '2026-10-05', $this->lines(), 'Uji', $this->owner);
        $this->assertSame('sale:42', $first->idempotency_key);

        $second = $this->posting->postOnce('sale', 42, '2026-10-05', $this->lines(), 'Uji', $this->owner);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Journal::where('source_type', 'sale')->where('source_id', 42)->count());

        // Penegakan DB nyata: sisipan manual berkunci sama ditolak.
        $this->expectException(QueryException::class);
        Journal::create([
            'number' => 'JU-DUPE', 'journal_date' => '2026-10-05', 'source_type' => 'sale',
            'source_id' => 42, 'idempotency_key' => 'sale:42', 'description' => 'dupe',
        ]);
    }

    public function test_other_posting_paths_stay_unrestricted(): void
    {
        // Penyusutan: banyak jurnal untuk (depreciation, aset) yang
        // sama lintas periode tetap sah lewat post() biasa.
        $this->posting->post('depreciation', 7, '2026-09-30', $this->lines(), 'Sep', $this->owner);
        $this->posting->post('depreciation', 7, '2026-10-31', $this->lines(), 'Okt', $this->owner);
        $this->assertSame(2, Journal::where('source_type', 'depreciation')->where('source_id', 7)->count());

        // Jurnal manual (source_id null) berkali-kali sah.
        $this->posting->post('manual', null, '2026-10-05', $this->lines(), 'A', $this->owner);
        $this->posting->post('manual', null, '2026-10-06', $this->lines(), 'B', $this->owner);
        $this->assertSame(2, Journal::where('source_type', 'manual')->count());
    }

    public function test_points_earn_per_sale_unique_but_redeem_repeatable(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        PointsLedger::create(['customer_id' => $customer->id, 'type' => 'earn', 'points' => 10, 'reference_type' => 'sale', 'reference_id' => 99]);

        // Redeem dua kali untuk penjualan yang sama: sah.
        PointsLedger::create(['customer_id' => $customer->id, 'type' => 'redeem', 'points' => -3, 'reference_type' => 'sale', 'reference_id' => 99]);
        PointsLedger::create(['customer_id' => $customer->id, 'type' => 'redeem', 'points' => -2, 'reference_type' => 'sale', 'reference_id' => 99]);
        $this->assertSame(2, PointsLedger::where('type', 'redeem')->where('reference_id', 99)->count());

        // Earn kedua untuk penjualan yang sama: ditolak basis data.
        $this->expectException(QueryException::class);
        PointsLedger::create(['customer_id' => $customer->id, 'type' => 'earn', 'points' => 10, 'reference_type' => 'sale', 'reference_id' => 99]);
    }
}
