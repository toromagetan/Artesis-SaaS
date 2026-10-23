<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $values['store.name'] = $values['store.name'] !== '' ? $values['store.name'] : 'Lentera';
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

        // Logo dokumen (PRD-Detail 12.5): berkas tunggal di public/,
        // dirujuk relatif dari aset web. Ganti = hapus berkas lama;
        // centang hapus = kembali tanpa logo (kepala cetak teks saja).
        if ($request->boolean('remove_logo')) {
            $this->deleteLogoFiles();
            Setting::set('store.logo_path', '');
            $map['store.logo_path'] = '';
        } elseif ($request->hasFile('store_logo')) {
            $file = $request->file('store_logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $this->deleteLogoFiles();
            $file->move(public_path(), 'store-logo.'.$ext);
            Setting::set('store.logo_path', 'store-logo.'.$ext);
            $map['store.logo_path'] = 'store-logo.'.$ext;
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

    private function deleteLogoFiles(): void
    {
        foreach (glob(public_path('store-logo.*')) ?: [] as $old) {
            @unlink($old);
        }
    }
}
