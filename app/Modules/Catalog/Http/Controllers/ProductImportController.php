<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Services\ProductExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman impor produk (A4): formulir unggah, unduh template,
 * dan eksekusi impor atomik. Izin products.create di rute.
 */
class ProductImportController extends Controller
{
    public function __construct(private readonly ProductExcelImportService $service) {}

    public function form(): View
    {
        return view('catalog.products.import');
    }

    public function template(): StreamedResponse
    {
        $bytes = $this->service->templateXlsx();

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, 'template-import-produk.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);

        $result = $this->service->import($request->user(), $request->file('file'));

        return redirect()->route('products.import.form')
            ->with('status', __('ui.product_import.success', ['count' => $result['created']]));
    }
}
