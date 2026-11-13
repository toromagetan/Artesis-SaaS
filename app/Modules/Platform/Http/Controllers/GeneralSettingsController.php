<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\TenantStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan Umum & Pajak (PRD-DETAIL §12.5; audit 23 Okt 2026 B2).
 * Hanya kunci yang benar-benar dibaca kode yang dikelola di sini:
 * identitas toko (nama tampil di kepala dokumen cetak; alamat &
 * telepon menyertainya), bawaan pajak penjualan, ambang diskon
 * tinggi, dan tarif komisi teknisi lab. Pengaturan poin member
 * tetap di layarnya sendiri. Izin: settings.manage.
 */
class GeneralSettingsController extends Controller
{
    /** @var list<string> */
    private const KEYS = [
        'store.name', 'store.address', 'store.phone', 'store.logo_path',
        'tax.rate_default', 'tax.mode',
        'sales.discount_threshold_percent', 'lab.technician_commission_rate',
    ];

    public function edit(): View
    {
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = Setting::value($key, '');
        }
        $values['store.name'] = $values['store.name'] !== '' ? $values['store.name'] : 'Artesis';
        $values['tax.mode'] = $values['tax.mode'] !== '' ? $values['tax.mode'] : 'exclusive';

        return view('settings.general', ['values' => $values]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_address' => ['nullable', 'string', 'max:300'],
            'store_phone' => ['nullable', 'string', 'max:30'],
            'tax_rate_default' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_mode' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'discount_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'technician_commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'store_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $map = [
            'store.name' => $validated['store_name'],
            'store.address' => $validated['store_address'] ?? '',
            'store.phone' => $validated['store_phone'] ?? '',
            'tax.rate_default' => (string) $validated['tax_rate_default'],
            'tax.mode' => $validated['tax_mode'],
            'sales.discount_threshold_percent' => (string) $validated['discount_threshold'],
            'lab.technician_commission_rate' => (string) $validated['technician_commission_rate'],
        ];
        foreach ($map as $key => $value) {
            Setting::set($key, $value);
        }

        // Logo dokumen (PRD-Detail 12.5) kini berkas per pemilik di
        // namespace disk publik (F-01): nilai pengaturan per tenant
        // menunjuk berkas ember tenant itu sendiri — logo satu
        // tenant tidak lagi menimpa logo tenant lain seperti saat
        // berkas tunggal di akar web. Berkas lama dihapus SETELAH
        // nilai baru tersimpan; centang hapus = tanpa logo.
        $previousLogo = (string) Setting::value('store.logo_path', '');
        if ($request->boolean('remove_logo')) {
            Setting::set('store.logo_path', '');
            $map['store.logo_path'] = '';
            $this->deleteLogoFile($previousLogo);
        } elseif ($request->hasFile('store_logo')) {
            $file = $request->file('store_logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            // Nama UNIK per unggahan (koreksi audit F-01-R1): nama
            // tetap membuat unggahan berekstensi sama menimpa berkas
            // lama di path yang sama SEBELUM nilai baru tersimpan.
            // Kini: berkas baru ditulis ke path baru → nilai disimpan
            // → berkas lama dihapus; bila penyimpanan nilai gagal,
            // berkas baru dibersihkan dan berkas lama utuh.
            $path = $file->storeAs(
                TenantStorage::directory($request->user(), 'settings'),
                'store-logo-'.Str::random(12).'.'.$ext,
                'public',
            );
            if (! is_string($path) || $path === '') {
                throw new \RuntimeException('Berkas logo gagal disimpan ke penyimpanan.');
            }
            try {
                Setting::set('store.logo_path', $path);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($path);
                throw $e;
            }
            $map['store.logo_path'] = $path;
            if ($previousLogo !== '' && $previousLogo !== $path) {
                $this->deleteLogoFile($previousLogo);
            }
        }

        // Nilai pajak/identitas finansial sensitif — catat kunci yang
        // berubah TANPA isi kredensial apa pun (tak ada di sini).
        app(AuditLogger::class)->log(
            action: 'settings.general.update',
            subjectType: 'setting',
            newValues: ['keys' => array_keys($map)],
            actor: $request->user(),
        );

        return redirect()->route('settings.general.edit')
            ->with('status', __('ui.settings_general.saved'));
    }

    /** Hapus tepat berkas logo yang dirujuk nilai sebelumnya. */
    private function deleteLogoFile(string $path): void
    {
        if ($path === '') {
            return;
        }
        if (str_contains($path, '/')) {
            Storage::disk('public')->delete($path);
        } else {
            // Warisan pra-F-01: nama berkas polos di akar web.
            @unlink(public_path($path));
        }
    }
}
