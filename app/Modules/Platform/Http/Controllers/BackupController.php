<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pengaturan → Backup & Restore. Semua aksi sepagar
 * settings.manage; RESTORE khusus Owner (peran diperiksa
 * eksplisit) + kata konfirmasi, karena menimpa seluruh data.
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('settings.backups.index', [
            'entries' => $this->backups->entries(),
            'offsiteEnabled' => filter_var(Setting::value('backup.offsite_enabled', '0'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /** Sakelar unggahan offsite — satu-satunya penulis kunci
     *  backup.offsite_enabled yang dibaca jadwal backup otomatis. */
    public function offsite(Request $request): RedirectResponse
    {
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
        $name = $this->backups->create($request->user());

        return back()->with('status', __('ui.backups.created', ['name' => $name]));
    }

    public function download(Request $request): BinaryFileResponse
    {
        $path = $this->backups->path((string) $request->query('berkas', ''));
        abort_if($path === null, 404);

        return response()->download($path);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['berkas' => ['required', 'string']]);
        $this->backups->delete($request->user(), (string) $validated['berkas']);

        return back()->with('status', __('ui.backups.deleted'));
    }

    public function restore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Owner'), 403);
        $validated = $request->validate([
            'berkas' => ['required', 'string'],
            'konfirmasi' => ['required', 'in:RESTORE'],
        ]);
        $result = $this->backups->restore($request->user(), (string) $validated['berkas']);

        return back()->with('status', __('ui.backups.restored', [
            'tables' => $result['restored_tables'],
            'safety' => $result['safety_backup'],
        ]));
    }
}
