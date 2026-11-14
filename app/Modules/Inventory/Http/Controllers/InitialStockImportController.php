<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Services\InitialStockImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impor stok awal (PRD 7.5): semua aksinya dipagari Gate
 * stock.import-initial (policy Owner non-platform — D.8).
 */
class InitialStockImportController extends Controller
{
    public function __construct(private readonly InitialStockImportService $service) {}

    public function form(): View
    {
        Gate::authorize('stock.import-initial');

        return view('inventory.import.form');
    }

    public function template(): Response
    {
        Gate::authorize('stock.import-initial');

        return response($this->service->templateXlsx(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-stok-awal.xlsx"',
        ]);
    }

    public function preview(Request $request): View
    {
        Gate::authorize('stock.import-initial');
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:2048']]);

        $preview = $this->service->previewUpload($request->file('file'), $request->user());

        return view('inventory.import.preview', compact('preview'));
    }

    public function commit(Request $request): RedirectResponse
    {
        Gate::authorize('stock.import-initial');
        $validated = $request->validate(['token' => ['required', 'string']]);

        $this->service->commit($request->user(), $validated['token']);

        return redirect()->route('stock.index')->with('status', __('ui.import.committed'));
    }
}
