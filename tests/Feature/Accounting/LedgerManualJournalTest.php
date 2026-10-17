<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1.2: buku besar (saldo awal/berjalan/akhir + filter cabang +
 * telusur ke jurnal sumber) dan jurnal manual (seimbang wajib,
 * akun ringkasan ditolak, pagar izin, periode terkunci ditolak).
 */
class LedgerManualJournalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::firstOrFail();
    }

    private function postJournal(string $source, string $date, array $lines): Journal
    {
        return app(PostingService::class)->post($source, null, $date, $lines, 'uji', $this->owner);
    }

    public function test_ledger_opening_running_and_closing_balances(): void
    {
        $kas = Account::where('code', '1-1100')->firstOrFail();
        $modal = '3-1000';

        // Bulan lalu: masuk 1.000.000
        $this->postJournal('opening', '2026-09-05', [
            ['account' => '1-1100', 'debit' => '1000000'],
            ['account' => $modal, 'credit' => '1000000'],
        ]);
        // Bulan ini: masuk 250.000, keluar 100.000
        $j2 = $this->postJournal('in', '2026-10-03', [
            ['account' => '1-1100', 'debit' => '250000', 'branch_id' => $this->branch->id],
            ['account' => '4-1100', 'credit' => '250000', 'branch_id' => $this->branch->id],
        ]);
        $this->postJournal('out', '2026-10-10', [
            ['account' => '5-1500', 'debit' => '100000'],
            ['account' => '1-1100', 'credit' => '100000'],
        ]);

        $ledger = app(LedgerService::class)->ledger($kas, '2026-10-01', '2026-10-31');

        $this->assertSame('1000000.00', $ledger['opening']->toDecimalString());
        $this->assertCount(2, $ledger['entries']);
        $this->assertSame('1250000.00', $ledger['entries'][0]['balance']->toDecimalString());
        $this->assertSame($j2->id, $ledger['entries'][0]['journal_id']);
        $this->assertSame('1150000.00', $ledger['closing']->toDecimalString());
        $this->assertSame('250000.00', $ledger['total_debit']->toDecimalString());
        $this->assertSame('100000.00', $ledger['total_credit']->toDecimalString());

        // Filter cabang: hanya baris berdimensi cabang itu.
        $branchLedger = app(LedgerService::class)->ledger($kas, '2026-10-01', '2026-10-31', $this->branch->id);
        $this->assertCount(1, $branchLedger['entries']);
        $this->assertSame('250000.00', $branchLedger['closing']->toDecimalString());

        // Akun sisi kredit (pendapatan): saldo bertambah di kredit.
        $pendapatan = Account::where('code', '4-1100')->firstOrFail();
        $revLedger = app(LedgerService::class)->ledger($pendapatan, '2026-10-01', '2026-10-31');
        $this->assertSame('250000.00', $revLedger['closing']->toDecimalString());

        // Halaman buku besar tampil + tautan telusur ke jurnal sumber.
        $this->actingAs($this->owner)
            ->get(route('ledger.index', ['akun' => $kas->id, 'dari' => '2026-10-01', 'sampai' => '2026-10-31']))
            ->assertOk()
            ->assertSee($j2->number)
            ->assertSee(route('journals.show', $j2->id), false);
    }

    public function test_manual_journal_balanced_is_posted_and_audited(): void
    {
        $kas = Account::where('code', '1-1100')->firstOrFail();
        $beban = Account::where('code', '5-1500')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('journals.store'), [
            'journal_date' => '2026-10-05',
            'description' => 'Koreksi kas kecil',
            'lines' => [
                ['account_id' => $beban->id, 'debit' => '75000', 'credit' => ''],
                ['account_id' => $kas->id, 'debit' => '', 'credit' => '75000'],
            ],
        ]);

        $journal = Journal::where('source_type', 'manual')->first();
        $this->assertNotNull($journal);
        $response->assertRedirect(route('journals.show', $journal->id));
        $this->assertSame($this->owner->id, $journal->created_by);
        $this->assertSame('75000.00', (string) $journal->lines()->sum('debit'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'journals.manual', 'subject_id' => $journal->id]);
    }

    public function test_manual_journal_unbalanced_and_single_line_are_rejected(): void
    {
        $kas = Account::where('code', '1-1100')->firstOrFail();
        $beban = Account::where('code', '5-1500')->firstOrFail();

        $this->actingAs($this->owner)->post(route('journals.store'), [
            'journal_date' => '2026-10-05',
            'lines' => [
                ['account_id' => $beban->id, 'debit' => '75000'],
                ['account_id' => $kas->id, 'credit' => '50000'],
            ],
        ])->assertSessionHasErrors('lines');
        $this->assertSame(0, Journal::count());

        $this->actingAs($this->owner)->post(route('journals.store'), [
            'journal_date' => '2026-10-05',
            'lines' => [
                ['account_id' => $beban->id, 'debit' => '75000'],
            ],
        ])->assertSessionHasErrors('lines');
        $this->assertSame(0, Journal::count());
    }

    public function test_manual_journal_rejects_summary_account_and_locked_period(): void
    {
        $induk = Account::where('code', '1-1100')->firstOrFail();
        $anak = Account::create(['code' => '1-1101', 'name' => 'Kas Kecil', 'group' => 'asset', 'parent_id' => $induk->id, 'is_active' => true]);
        $kas = Account::where('code', '1-1200')->firstOrFail();

        // 1-1100 kini akun ringkasan → jurnal manual ke sana ditolak.
        $this->actingAs($this->owner)->post(route('journals.store'), [
            'journal_date' => '2026-10-05',
            'lines' => [
                ['account_id' => $induk->id, 'debit' => '10000'],
                ['account_id' => $kas->id, 'credit' => '10000'],
            ],
        ])->assertSessionHasErrors('lines.0.account_id');
        $this->assertSame(0, Journal::count());

        // Periode terkunci → posting manual ditolak menyebut periodenya.
        app(PostingService::class)->lockPeriod($this->owner, '2026-10');
        $this->actingAs($this->owner)->post(route('journals.store'), [
            'journal_date' => '2026-10-06',
            'lines' => [
                ['account_id' => $anak->id, 'debit' => '10000'],
                ['account_id' => $kas->id, 'credit' => '10000'],
            ],
        ])->assertSessionHasErrors('period');
        $this->assertSame(0, Journal::count());
    }

    public function test_manual_journal_and_ledger_permission_fences(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $kasir = $this->makeUserWithRole('Kasir');
        $platform = User::where('username', 'platform')->firstOrFail();

        // Admin: boleh buku besar & jurnal (baca), TIDAK jurnal manual.
        $this->actingAs($admin)->get(route('ledger.index'))->assertOk();
        $this->actingAs($admin)->get(route('journals.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('journals.store'), [])->assertForbidden();

        // Kasir & platform: semua tertutup.
        $this->actingAs($kasir)->get(route('ledger.index'))->assertForbidden();
        $this->actingAs($platform)->get(route('ledger.index'))->assertForbidden();
        $this->actingAs($platform)->get(route('journals.create'))->assertForbidden();
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::create([
            'name' => $roleName, 'username' => strtolower($roleName).'-f12',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        $user->branches()->attach($this->branch);

        return $user;
    }
}
