<?php

namespace App\Support;

use App\Modules\Master\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Unduh dokumen cetak sebagai berkas PDF (keputusan owner
 * 15 Nov 2026): berkas yang sama tampilannya dengan halaman cetak,
 * untuk dilampirkan staf secara manual pada aplikasi WhatsApp
 * (skema Kirim WA wa.me) atau saluran lain.
 */
class DocumentPdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function download(string $view, array $data, string $filename, string $paper = 'a5', string $orientation = 'portrait'): Response
    {
        if (! array_key_exists('storeLogo', $data)) {
            $logo = self::logoDataUri();
            if ($logo !== null) {
                $data['storeLogo'] = $logo;
            }
        }

        return Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation)
            ->download($filename);
    }

    /**
     * Logo toko sebagai data URI agar tampil di PDF (dompdf tidak
     * memuat URL web); null bila logo tidak diatur/berkas hilang —
     * dokumen tetap terbit tanpa logo, sama seperti halaman cetak
     * tanpa logo.
     */
    private static function logoDataUri(): ?string
    {
        $path = Setting::value('store.logo_path');

        if (! is_string($path) || $path === '') {
            return null;
        }

        $absolute = str_contains($path, '/')
            ? Storage::disk('public')->path($path)
            : public_path($path);

        if (! is_file($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';
        $contents = file_get_contents($absolute);

        return $contents === false ? null : 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
