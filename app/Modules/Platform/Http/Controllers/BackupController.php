<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
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
        return view('settings.backups.index', ['entries' => $this->backups->entries()]);
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
