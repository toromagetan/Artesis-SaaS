<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Policies\BackupPolicy;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pengaturan → Backup & Restore database global.
 *
 * Dua lapis otorisasi (R-1, menutup temuan audit P0-1):
 * 1. Rute digerbang izin khusus `backups.manage` (bukan lagi
 *    `settings.manage`).
 * 2. Setiap aksi melewati BackupPolicy (aturan mode): aktor tenant
 *    DITOLAK selalu — backup adalah snapshot seluruh database,
 *    bukan hak tenant; aktor platform diizinkan; aktor individual
 *    (tanpa tenant) diizinkan selama instalasinya benar-benar
 *    individual (tidak ada baris tenant).
 *
 * Penolakan policy menghasilkan 403 dan tercatat audit
 * `backup.denied`. Unduhan yang berhasil tercatat `backup.downloaded`.
 * RESTORE: ketik RESTORE + (aktor platform pada instansi bertenant)
 * menyebut jumlah tenant terdampak sebagai konfirmasi dampak.
 */
class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly BackupPolicy $policy,
    ) {}

    /**
     * Gerbang mode terpusat untuk semua aksi backup. Penolakan
     * selalu teraudit sebelum 403 dilempar.
     */
    private function authorizeBackup(Request $request, string $operation, ?string $file = null, bool $restore = false): void
    {
        $user = $request->user();
        $allowed = $restore ? $this->policy->restore($user) : $this->policy->access($user);

        if ($allowed) {
            return;
        }

        app(AuditLogger::class)->log(
            action: 'backup.denied',
            subjectType: 'Backup',
            subjectId: null,
            newValues: ['operation' => $operation, 'file' => $file],
            actor: $user,
        );

        abort(403, __('ui.backups.forbidden'));
    }

    public function index(Request $request): View
    {
        $this->authorizeBackup($request, 'list');

        return view('settings.backups.index', [
            'entries' => $this->backups->entries(),
            'offsiteEnabled' => filter_var(Setting::value('backup.offsite_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
            'tenantCount' => Tenant::count(),
            'canRestore' => $this->policy->restore($request->user()),
        ]);
    }

    /** Sakelar unggahan offsite — satu-satunya penulis kunci
     *  backup.offsite_enabled yang dibaca jadwal backup otomatis. */
    public function offsite(Request $request): RedirectResponse
    {
        $this->authorizeBackup($request, 'offsite');

        $aktif = $request->boolean('aktif');
        Setting::set('backup.offsite_enabled', $aktif ? '1' : '0');
        app(AuditLogger::class)->log(
            action: 'backup.offsite_toggle',
            subjectType: 'Backup',
            subjectId: null,
            newValues: ['enabled' => $aktif],
        );

        return back()->with('status', __('ui.backups.offsite_saved'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeBackup($request, 'create');

        $name = $this->backups->create($request->user());

        return back()->with('status', __('ui.backups.created', ['name' => $name]));
    }

    public function download(Request $request): BinaryFileResponse
    {
        $file = (string) $request->query('berkas', '');
        $this->authorizeBackup($request, 'download', $file !== '' ? $file : null);

        $path = $this->backups->path($file);
        abort_if($path === null, 404);

        app(AuditLogger::class)->log(
            action: 'backup.downloaded',
            subjectType: 'Backup',
            subjectId: null,
            newValues: ['file' => basename($path)],
            actor: $request->user(),
        );

        return response()->download($path);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['berkas' => ['required', 'string']]);
        $this->authorizeBackup($request, 'delete', (string) $validated['berkas']);

        $this->backups->delete($request->user(), (string) $validated['berkas']);

        return back()->with('status', __('ui.backups.deleted'));
    }

    public function restore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'berkas' => ['required', 'string'],
            'konfirmasi' => ['required', 'in:RESTORE'],
        ]);
        $this->authorizeBackup($request, 'restore', (string) $validated['berkas'], restore: true);

        // Perlindungan dampak untuk aktor platform: pada instansi
        // yang melayani tenant, restore menimpa data SEMUA tenant —
        // aktor wajib menyebut jumlah tenant terdampak yang benar.
        // Ini konfirmasi dampak, BUKAN pengganti otorisasi di atas.
        if ($request->user()->is_platform && ($tenantCount = Tenant::count()) > 0) {
            $request->validate([
                'jumlah_tenant' => ['required', 'integer', 'in:'.$tenantCount],
            ]);
        }

        $result = $this->backups->restore($request->user(), (string) $validated['berkas']);

        return back()->with('status', __('ui.backups.restored', [
            'tables' => $result['restored_tables'],
            'safety' => $result['safety_backup'],
        ]));
    }
}
