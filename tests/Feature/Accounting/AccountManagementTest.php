<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\AccountService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F1.1 CoA & bagan akun: kelola akun (buat/ubah/nonaktif — tanpa
 * hapus), kode terkunci sesudah berbaris jurnal, pagar accounts.*.
 */
class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->admin = User::factory()->create();
        $this->admin->roles()->sync([Role::where('name', 'admin')->firstOrFail()->id]);
    }

    public function test_owner_creates_child_account_and_audit_written(): void
    {
        $kas = Account::where('code', '1-1100')->firstOrFail();

        $this->actingAs($this->owner)->post('/akun', [
            'code' => '1-1101', 'name' => 'Kas Cabang', 'group' => 'asset', 'parent_id' => $kas->id,
        ])->assertRedirect();

        $child = Account::where('code', '1-1101')->firstOrFail();
        $this->assertSame($kas->id, $child->parent_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'accounts.create', 'subject_id' => $child->id]);
    }

    public function test_duplicate_code_and_cross_group_parent_are_rejected(): void
    {
        $this->actingAs($this->owner)->post('/akun', [
            'code' => '1-1100', 'name' => 'Duplikat', 'group' => 'asset',
        ])->assertSessionHasErrors('code');

        $utang = Account::where('code', '2-1100')->firstOrFail();
        $this->actingAs($this->owner)->post('/akun', [
            'code' => '1-9900', 'name' => 'Salah Kelompok', 'group' => 'asset', 'parent_id' => $utang->id,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_account_with_journal_lines_locks_code_group_parent_but_not_name(): void
    {
        app(PostingService::class)->post('manual', null, '2026-10-05', [
            ['account' => '1-1100', 'debit' => '1000'],
            ['account' => '3-1000', 'credit' => '1000'],
        ], 'Setoran modal uji', $this->owner);

        $kas = Account::where('code', '1-1100')->firstOrFail();

        $renamed = app(AccountService::class)
            ->update($this->owner, $kas, ['name' => 'Kas Besar']);
        $this->assertSame('Kas Besar', $renamed->name);

        try {
            app(AccountService::class)
                ->update($this->owner, $kas, ['code' => '1-9999']);
            $this->fail('Perubahan kode akun berbaris jurnal harus ditolak');
        } catch (ValidationException) {
            $this->assertSame('1-1100', $kas->refresh()->code);
        }
    }

    public function test_deactivated_account_rejects_new_postings_and_parent_with_active_children_cannot_deactivate(): void
    {
        $posting = app(PostingService::class);
        $kas = Account::where('code', '1-1100')->firstOrFail();

        app(AccountService::class)->setActive($this->owner, $kas, false);

        try {
            $posting->post('manual', null, '2026-10-05', [
                ['account' => '1-1100', 'debit' => '1000'],
                ['account' => '3-1000', 'credit' => '1000'],
            ], 'Harus gagal', $this->owner);
            $this->fail('Posting ke akun nonaktif harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, Journal::count());
        }

        // Induk beranak aktif tidak dapat dinonaktifkan.
        $parent = Account::create(['code' => '1-9000', 'name' => 'Induk Uji', 'group' => 'asset', 'is_active' => true]);
        Account::create(['code' => '1-9001', 'name' => 'Anak Uji', 'group' => 'asset', 'parent_id' => $parent->id, 'is_active' => true]);
        try {
            app(AccountService::class)->setActive($this->owner, $parent, false);
            $this->fail('Nonaktif induk beranak aktif harus ditolak');
        } catch (ValidationException) {
            $this->assertTrue($parent->refresh()->is_active);
        }
    }

    public function test_permissions_gate_management_but_allow_reads(): void
    {
        // Admin: accounts.view + journals.view, TANPA accounts.manage.
        $this->actingAs($this->admin)->get('/akun')->assertOk();
        $this->actingAs($this->admin)->get('/akun/'.Account::firstOrFail()->id)->assertOk();
        $this->actingAs($this->admin)->post('/akun', [
            'code' => '9-9999', 'name' => 'Ilegal', 'group' => 'asset',
        ])->assertForbidden();

        // Kasir: tanpa izin akuntansi sama sekali.
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->actingAs($kasir)->get('/akun')->assertForbidden();

        // Platform: ditolak.
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($platform)->get('/akun')->assertForbidden();

        // Tanpa rute hapus (DELETE → 405).
        $this->actingAs($this->owner)->delete('/akun/'.Account::firstOrFail()->id)->assertStatus(405);
    }
}
