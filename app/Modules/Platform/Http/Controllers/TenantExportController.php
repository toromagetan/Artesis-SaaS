<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Policies\TenantExportPolicy;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\TenantExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pengaturan → Ekspor Data (per tenant, B-03).
 *
 * Tenant sasaran TIDAK pernah dipercaya dari input untuk aktor
 * tenant: aktor tenant selalu mengekspor tenantnya sendiri.
 * Aktor platform memilih tenant sasaran (atas nama) lewat
 * parameter tenant_id. Setiap aksi melewati TenantExportPolicy;
 * penolakan teraudit `tenant_export.denied`, unduhan yang
 * berhasil teraudit `tenant_export.downloaded`.
 */
class TenantExportController extends Controller
{
    public function __construct(
        private readonly TenantExportService $exports,
        private readonly TenantExportPolicy $policy,
    ) {}

    /**
     * Tenant sasaran untuk aktor ini. Platform: dari input
     * (boleh kosong pada index = tampilkan pemilih). Tenant:
     * selalu tenantnya sendiri. Lainnya: null (ditolak).
     */
    private function targetTenant(Request $request, bool $required = true): ?Tenant
    {
        $user = $request->user();
        if ($user->is_platform) {
            $tenantId = $request->integer('tenant_id') ?: $request->integer('tenant');

            return $tenantId ? Tenant::find($tenantId) : null;
        }
        $context = TenantContext::for($user);

        return $context->isValid() ? $context->tenant() : null;
    }

    private function authorizeExport(Request $request, Tenant $tenant, string $operation, ?string $file = null): void
    {
        if ($this->policy->export($request->user(), $tenant)) {
            return;
        }
        app(AuditLogger::class)->log(
            action: 'tenant_export.denied',
            subjectType: 'Tenant',
            subjectId: (int) $tenant->id,
            newValues: ['operation' => $operation, 'file' => $file],
            actor: $request->user(),
        );
        abort(403, __('ui.tenant_exports.forbidden'));
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        // Platform tanpa tenant terpilih: pemilih tenant.
        if ($user->is_platform && ! $request->filled('tenant_id') && ! $request->filled('tenant')) {
            return view('settings.tenant-exports.index', [
                'tenants' => Tenant::orderBy('name')->get(),
                'tenant' => null,
                'entries' => [],
            ]);
        }
        $tenant = $this->targetTenant($request, required: true);
        abort_if($tenant === null, 403, __('ui.tenant_exports.forbidden'));
        $this->authorizeExport($request, $tenant, 'list');

        return view('settings.tenant-exports.index', [
            'tenants' => $user->is_platform ? Tenant::orderBy('name')->get() : collect(),
            'tenant' => $tenant,
            'entries' => $this->exports->entries($tenant),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->targetTenant($request, required: true);
        abort_if($tenant === null, 403, __('ui.tenant_exports.forbidden'));
        $this->authorizeExport($request, $tenant, 'create');

        $name = $this->exports->create($request->user(), $tenant);

        return redirect()->route('tenant-exports.index', $request->user()->is_platform ? ['tenant_id' => $tenant->id] : [])
            ->with('status', __('ui.tenant_exports.created', ['name' => $name]));
    }

    public function download(Request $request): BinaryFileResponse
    {
        $tenant = $this->targetTenant($request, required: true);
        abort_if($tenant === null, 403, __('ui.tenant_exports.forbidden'));
        $file = (string) $request->query('berkas', '');
        $this->authorizeExport($request, $tenant, 'download', $file !== '' ? $file : null);

        $path = $this->exports->path($tenant, $file);
        abort_if($path === null, 404);

        app(AuditLogger::class)->log(
            action: 'tenant_export.downloaded',
            subjectType: 'Tenant',
            subjectId: (int) $tenant->id,
            newValues: ['file' => basename($path)],
            actor: $request->user(),
        );

        return response()->download($path);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $tenant = $this->targetTenant($request, required: true);
        abort_if($tenant === null, 403, __('ui.tenant_exports.forbidden'));
        $validated = $request->validate(['berkas' => ['required', 'string']]);
        $this->authorizeExport($request, $tenant, 'delete', (string) $validated['berkas']);

        $this->exports->delete($request->user(), $tenant, (string) $validated['berkas']);

        return back()->with('status', __('ui.tenant_exports.deleted'));
    }
}
