# Lentera

![Logo Lentera](assets/logo.webp)

**Jernih Datanya, Terang Bisnisnya.**

Lentera adalah sistem manajemen & POS toko optik dengan model **kepemilikan penuh**: satu paket utuh tanpa tier, self-hosted di infrastruktur pemilik bisnis, data 100% milik pemilik — menggabungkan kedalaman domain yang terbukti di Artesis dengan keluasan fitur setara SaaS optik modern (akuntansi penuh, QC faset & order lab, WhatsApp, membership, analitik).

- Repositori ini **mandiri** — terpisah penuh dari Artesis; Artesis tidak disentuh oleh proyek ini.
- Dokumen produk: [docs/PRD.md](docs/PRD.md) (PRD utama) · [docs/PRD-DETAIL.md](docs/PRD-DETAIL.md) (spesifikasi menu per menu) · [docs/BLUEPRINT.md](docs/BLUEPRINT.md) (blueprint arsitektur) · [docs/ROADMAP.md](docs/ROADMAP.md) (roadmap pembangunan bergerbang)
- Status: **seluruh fase terbangun** (POS, pembelian, stok FIFO, lab & QC, akuntansi penuh, WhatsApp, membership, API baca-saja, mode offline, Asisten AI, Fitting AI) — lihat Roadmap untuk gerbang & bukti tiap fase.

## Persyaratan

- PHP **8.3** dengan ekstensi sesuai `composer.json` (`ext-gd`, `ext-zip`, `ext-intl`, `ext-mbstring`, `ext-pdo_mysql`, dll.). Untuk pemasangan yang menghadap internet, gunakan build PHP yang sudah ditambal terhadap kerentanan runtime terkini.
- MySQL **8.0**, Composer 2, Node 20+ (hanya untuk membangun aset Vite/Tailwind).

## Instalasi

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# atur DB_* di .env ke database MySQL Anda
php artisan migrate --seed
php artisan serve   # pengembangan lokal
```

Kunci lingkungan penting ada di `.env.example` (database, antrean, gateway WA diatur dari UI Pengaturan — bukan dari `.env`).

## Proses latar yang WAJIB berjalan di produksi

Dua proses ini adalah bagian dari produk, bukan pelengkap:

1. **Scheduler** — jalankan tiap menit lewat cron:

   ```cron
   * * * * * cd /path/ke/lentera && php artisan schedule:run >> /dev/null 2>&1
   ```

   Menggerakkan: otomasi WhatsApp harian 08:00 (pengingat kontrol, cicilan, after-service), sapu antrean WA tiap jam, backup otomatis harian 02:00 (retensi 14), penyusutan aset tanggal 1 pukul 01:00, dan kedaluwarsa poin 00:30.

2. **Queue worker** — antrean memakai driver `database`; kirim WhatsApp dan pekerjaan asinkron lain hanya berjalan bila worker hidup. Contoh unit systemd:

   ```ini
   [Service]
   ExecStart=/usr/bin/php /path/ke/lentera/artisan queue:work database --tries=3 --timeout=120
   Restart=always
   ```

   Dashboard memperingatkan pemegang izin Pengaturan bila ada job yang gagal (`failed_jobs`) atau backup basi/rusak.

## Backup & pemulihan

- Backup dibuat dari **Pengaturan → Backup & Restore** (manual) dan otomatis tiap hari; setiap berkas diverifikasi keterbacaannya dan statusnya tampil sebagai lencana Terverifikasi.
- Restore hanya untuk Owner, menimpa seluruh data, dan selalu didahului backup keselamatan otomatis (awalan `pra-restore`).
- Unggahan offsite (Google Drive pemilik) diaktifkan dari halaman yang sama setelah kredensial Service Account pemilik dipasang.

## Pengujian

```bash
php artisan test
```

Suite mencakup regresi penuh + gerbang konkurensi (basis data MySQL nyata `lentera_test`; test konkurensi membuat basis data unik per run berpola `lentera_conc_<pid>_<acak>` dan hanya menghapus buatannya sendiri — LNT-007; lihat `.env.testing` setempat). CI menjalankan pemindaian pola terlarang (`tools/scan-forbidden.sh` — antara lain larangan fungsi `bc*`), Pint, dan seluruh suite pada setiap push.
