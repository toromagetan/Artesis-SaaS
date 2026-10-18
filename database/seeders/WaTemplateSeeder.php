<?php

namespace Database\Seeders;

use App\Modules\Comms\Models\WaTemplate;
use Illuminate\Database\Seeder;

/**
 * Template WhatsApp bawaan (F3.4, kunci Blueprint B.7).
 * firstOrCreate per key: penanaman ulang tidak menimpa suntingan
 * Admin di layar Template.
 */
class WaTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'invoice' => 'Halo {nama}, terima kasih sudah berbelanja di {cabang}. Transaksi {nomor} sebesar {total} telah kami catat. Simpan pesan ini sebagai bukti transaksi Anda.',
            'pickup_reminder' => 'Halo {nama}, kacamata untuk transaksi {nomor} sudah selesai dan dapat diambil di {cabang}. Kami tunggu kedatangan Anda.',
            'after_service' => 'Halo {nama}, semoga kacamata dari transaksi {nomor} nyaman dipakai. Bila ada yang kurang pas, balas pesan ini — kami siap membantu penyesuaian tanpa biaya.',
            'birthday' => 'Selamat ulang tahun, {nama}! Keluarga besar {cabang} mendoakan kesehatan dan penglihatan yang selalu jernih untuk Anda.',
            'campaign' => '{isi}',
        ];

        foreach ($templates as $key => $body) {
            WaTemplate::firstOrCreate(['key' => $key], ['body' => $body]);
        }
    }
}
