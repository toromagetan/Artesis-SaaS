# Artesis

![Logo Artesis](assets/logo.webp)

**Jernih Datanya, Terang Bisnisnya.**

Artesis adalah **SaaS POS Optik & Operational Management multi-tenant**: satu platform dipakai banyak perusahaan optik (tenant), masing-masing dengan data bisnis, cabang, dan penggunanya sendiri, di atas sistem operasional optik yang lengkap — penjualan & resep, proses lab & QC faset, pembelian ujung ke ujung, stok FIFO per cabang, akuntansi double-entry, WhatsApp, dan membership.

Satu basis kode yang sama juga dapat dipasang sebagai **instalasi individual** (satu bisnis, server & domain sendiri, tanpa lapisan paket) — aplikasi mengenali kedua mode ini dari konteks aktor (lihat [docs/BLUEPRINT.md](docs/BLUEPRINT.md)).

> **Catatan identitas:** produk ini sebelumnya bernama **Lentera** dan repositori ini sebelumnya `toromagetan/Lentera`. Nama produk diganti menjadi Artesis (Fase 1 SaaS, 2026-10-29) dan repositori diganti namanya menjadi `toromagetan/Artesis-SaaS` (2026-10-30). Repositori ini **bukan** `toromagetan/Artesis` (sistem self-hosted generasi sebelumnya, proyek terpisah yang tidak disentuh di sini). Penanda internal warisan nama lama (namespace komponen, nama database uji, perintah artisan `lentera:*`) adalah penanda teknis, bukan identitas produk.

## Model platform

```
Platform (dikelola Platform Superadmin)
└── Tenant (perusahaan optik pelanggan)
    ├── Subscription → Plan (Free / Solo / Team / Best Offer)
    ├── Branch (satu atau lebih cabang, sesuai Plan)
    └── User (Tenant Owner & pengguna operasional: Admin, Kasir, Gudang, dst.)
```

- **Platform Superadmin** mengelola platform: tenant, Plan, Subscription, Billing, dan operasional SaaS — dan **tidak** otomatis memperoleh akses ke data bisnis tenant.
- **Tenant Owner** mengelola bisnisnya sendiri: cabang, pengguna, peran, dan seluruh operasional tokonya.
- Penegakan Plan (fitur & batas angka) berlaku **di server** (`PlanGate` + middleware `plan.feature`), bukan sekadar menyembunyikan UI. Pada instalasi individual (aktor tanpa tenant), semua fitur menyala dan semua batas tidak berlaku.
- Bahasa antarmuka: **Indonesia, Inggris, Melayu, Mandarin** (`lang/id|en|ms|zh`).

## Fitur utama

- **Kasir & penjualan:** terminal POS satu halaman, resep terikat transaksi, precal manual 11 parameter (PDR/PDL hanya di resep — tidak pernah di precal), pembayaran bertahap/split/cicilan, DP, retur, garansi.
- **Lab & QC:** antrean proses lensa, QC faset 13 titik yang menggerbang penyelesaian, PR otomatis dari penjualan untuk kekurangan lensa.
- **Pembelian:** Purchase Request → Purchase Order → penerimaan bertahap → pembayaran tunggal & kolektif (FIFO, anti tercatat-sebagian) → retur pembelian.
- **Stok:** ledger `stock_movements` single-writer, costing FIFO per cabang, transfer antar cabang, opname, impor stok awal bergerbang policy, matriks lensa untuk PO & opname.
- **Akuntansi:** jurnal otomatis event-driven, buku besar, laporan keuangan (neraca, laba rugi total & per cabang, arus kas), aset & penyusutan, tutup buku per periode, payroll & insentif.
- **Pelanggan:** master pelanggan & resep, membership & poin (tercatat sebagai liabilitas), portal member ber-token, kredit toko dari refund.
- **Komunikasi:** WhatsApp (Meta Cloud API) — invoice, pengingat pengambilan/after-service/ulang tahun, inbox dua arah, kampanye berpagar persetujuan.
- **Platform SaaS:** pendaftaran mandiri (`/daftar`), panel Platform Superadmin (tenant, paket, penagihan), halaman **Paket Saya** untuk tenant, penagihan transfer manual dengan konfirmasi platform.
- **Lainnya:** API baca-saja ber-token, mode kasir offline, Asisten AI bisnis (khusus Owner), Fitting AI, audit log mutasi.

Status implementasi per fitur — termasuk yang **belum** selesai — dicatat apa adanya di [docs/PRD.md](docs/PRD.md). Dokumen itu, bukan README ini, adalah acuan status.

## Persyaratan

- PHP **8.3+** (`composer.json`: `^8.3`) dengan ekstensi: `ext-curl, ext-fileinfo, ext-gd, ext-intl, ext-json, ext-mbstring, ext-openssl, ext-pdo_mysql, ext-xml, ext-zip`. **Peringatan:** `ext-bcmath` secara eksplisit **tidak** dipakai aplikasi ini dan tidak boleh dipanggil (pola `bc*` ditolak pemindai CI). Untuk pemasangan yang menghadap internet, gunakan build PHP yang sudah ditambal terhadap kerentanan runtime terkini.
- MySQL **8.0**, Composer 2, Node 20+ (hanya untuk membangun aset Vite/Tailwind).
- Framework: Laravel **13.x** (terkunci di `composer.lock`).

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

Seeder bawaan menanam peran & izin, bagan akun, titik QC, **dan lapisan SaaS** (Plan + peran Platform) — instalasi segar langsung utuh. **WAJIB sesudah instalasi:** ganti kata sandi bawaan akun `owner` dan `platform` (keduanya `password` dari seeder) sebelum instalasi dibuka ke siapa pun, dan set `APP_DEBUG=false` di produksi. Prosedur pemasangan produksi lengkap — dua topologi, nginx, cron, queue worker, backup, dan smoke test 12 poin — ada di [docs/DEPLOY-RUNBOOK.md](docs/DEPLOY-RUNBOOK.md).

Gambaran konfigurasi `.env` (nama kunci saja; nilai rahasia tidak pernah masuk repositori): aplikasi (`APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL`), database (`DB_*`), sesi (`SESSION_*`), antrean & cache (`QUEUE_CONNECTION, CACHE_STORE`), surat (`MAIL_*`). Kredensial gateway WhatsApp dan AI **tidak** diatur dari `.env` — keduanya diatur dari UI Pengaturan dan tersimpan terenkripsi.

## Proses latar yang WAJIB berjalan di produksi

1. **Scheduler** — tiap menit lewat cron:

   ```cron
   * * * * * cd /path/ke/artesis && php artisan schedule:run >> /dev/null 2>&1
   ```

   Menggerakkan: otomasi WhatsApp harian 08:00, sapu antrean WA tiap jam, backup otomatis harian 02:00 (retensi 14), penyusutan aset tanggal 1 pukul 01:00, kedaluwarsa poin 00:30.

2. **Queue worker** — driver `database`; pengiriman WhatsApp dan pekerjaan asinkron lain hanya berjalan bila worker hidup. Contoh unit systemd tersedia di [docs/server/artesis-queue.service](docs/server/artesis-queue.service); contoh konfigurasi nginx di [docs/server/nginx-artesis.conf](docs/server/nginx-artesis.conf).

## Backup & pemulihan — baca batasannya

- Backup adalah **snapshot seluruh database satu instalasi** (semua tabel), dibuat manual dari Pengaturan → Backup & Restore atau otomatis tiap hari; setiap berkas diverifikasi keterbacaannya. Restore menimpa seluruh data instalasi dan selalu didahului backup keselamatan (`pra-restore`).
- Dalam **mode SaaS satu instansi bersama**, snapshot itu memuat data **semua** tenant — karena itu backup/restore adalah operasi tingkat platform/instalasi (digerbang izin `backups.manage` + policy berbasis mode; aktor tenant selalu ditolak), **bukan** fitur per tenant. Kebutuhan tenant atas datanya sendiri dilayani **Ekspor Data per tenant** (Pengaturan → Ekspor Data): Tenant Owner mengunduh salinan lengkap data tenantnya sendiri — tersaring per kepemilikan, tanpa kredensial, teraudit. Restore per tenant diputuskan tidak dibangun.

## Prosedur migrasi indeks unik reversal_of_id (LNT-005)

Migrasi `2026_10_23_000045` memasang indeks unik pada `journals.reversal_of_id`. Pada database yang datanya berasal dari sebelum perbaikan LNT-005, indeks itu **gagal terpasang (ERROR 1062)** bila sudah ada jurnal asal dengan lebih dari satu jurnal pembalik. Jalankan prosedur ini sebelum `php artisan migrate` pada database lama mana pun:

1. **Preflight (read-only, wajib lulus):** `php artisan lentera:preflight-reversal` — exit 0 dan pesan `AMAN` berarti tidak ada duplikat. Exit 1 berarti duplikat ditemukan dan dilaporkan per jurnal asal; **jangan migrasi dulu**.
2. **Backup:** buat backup dari **Pengaturan → Backup & Restore** dan pastikan statusnya Terverifikasi.
3. Bila preflight gagal: bersihkan duplikat secara manual dari salinan backup yang terverifikasi (sisakan tepat satu jurnal pembalik per jurnal asal), pulihkan, lalu ulangi langkah 1.
4. **Migrasi:** `php artisan migrate` — 000045 kini terpasang.
5. **Verifikasi indeks:** `SHOW INDEX FROM journals WHERE Column_name = 'reversal_of_id';` harus menampilkan satu indeks `Non_unique = 0`.

## Pengujian

```bash
php artisan test
```

Suite berjalan terhadap MySQL nyata (database uji `lentera_test`; test konkurensi membuat database unik per run berpola `lentera_conc_<pid>_<acak>` dan hanya menghapus buatannya sendiri — lihat `.env.testing` setempat). Cakupan relevan SaaS: `SaasEnforcementTest` (penegakan Plan), `SaasSignupBillingTest` (pendaftaran & penagihan), `UserTenantIsolationTest` (isolasi pengelolaan pengguna), `TenantProvisioningTest` (penyediaan tenant dari panel), `GoLiveReadinessTest`, matriks isolasi tenant Fase 0 (konteks, pagar master/transaksi/akuntansi, gudang garansi), dan `TenantExportIsolationTest` (ekspor per tenant). CI menjalankan Pint, pemindaian pola terlarang (`tools/scan-forbidden.sh`), unit, feature + matriks keamanan, dan build aset pada setiap PR; `main` terproteksi (PR wajib, CI hijau wajib).

## Dokumentasi utama

- [docs/PRD.md](docs/PRD.md) — kebutuhan produk SaaS, peran, status implementasi per fitur, persyaratan isolasi tenant.
- [docs/PRD-DETAIL.md](docs/PRD-DETAIL.md) — spesifikasi perilaku per menu (framing & identitasnya diselaraskan pada D-01).
- [docs/BLUEPRINT.md](docs/BLUEPRINT.md) — arsitektur aktual & target, klasifikasi kepemilikan data, arsitektur keamanan, status ADR.
- [docs/ROADMAP.md](docs/ROADMAP.md) — rencana berbasis dependensi: penyelarasan dokumentasi → fondasi isolasi tenant → isolasi data bisnis → siklus hidup SaaS → backup/ekspor → kesiapan produksi.
- [docs/DEPLOY-RUNBOOK.md](docs/DEPLOY-RUNBOOK.md) — runbook pemasangan & operasi produksi.

## Status proyek & batasan produksi yang masih terbuka

**Aplikasi ini belum dinyatakan production-ready untuk mode SaaS satu instansi bersama** — penilaian kesiapan produksi menyeluruh (Roadmap Fase 6) belum dilakukan. Keadaan terbukti saat ini (baseline `main` @ `43db5b54`, 2026-11-12):

- Lapisan platform SaaS (tenant, Plan, Subscription, Billing, pendaftaran, panel) **sudah terbangun dan teruji**.
- Seluruh fitur bisnis optik **sudah terbangun**.
- **Isolasi data bisnis antar-tenant di dalam satu instansi SUDAH terbangun** (Fase 0, PR #23–#35): seluruh data bisnis — termasuk master pelanggan, resep, dan produk — adalah milik tenant (keputusan OD-2: per tenant penuh; "global" pada ADR-012 kini berarti global **di dalam tenant**, lintas cabang satu tenant), terpagar konteks tenant + cabang, dan penegakan `tenants.is_active` berlaku pada login & akses. Batas kepercayaan baca in-process diatur [ADR-022](docs/adr/ADR-022-batas-kepercayaan-in-process.md).
- Keputusan topologi produksi (**OD-1**) masih **terbuka** di pemilik proyek — isolasi tidak lagi menjadi penghalang teknis mode satu instansi bersama, tetapi pilihan topologi dan penilaian kesiapan produksi tetap keputusan & gerbang tersendiri.
- Yang masih terbuka: pendaftaran publik belum memakai CAPTCHA/verifikasi email (anti-penyalahgunaan), backup offsite belum terimplementasi, awalan cache per tenant belum dikerjakan, angka kapasitas terukur belum ada.
