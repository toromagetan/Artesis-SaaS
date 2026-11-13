# PRD — ARTESIS: SaaS POS Optik & Operational Management

| | |
|---|---|
| Dokumen | Product Requirements Document (PRD) v2.0 — penyelarasan SaaS |
| Produk | **Artesis** — SaaS POS Optik & Operational Management (multi-tenant) |
| Repositori | `toromagetan/Artesis-SaaS`, branch `main` — **bukan** `toromagetan/Artesis` (sistem self-hosted generasi sebelumnya, proyek terpisah) |
| Baseline audit | `main` @ `43db5b54` (2026-11-12) — seluruh status implementasi di dokumen ini diverifikasi terhadap commit ini |
| Tanggal | 30 Oktober 2026; **diselaraskan D-01 pada 2026-11-12** (status Fase 0, keputusan OD-2, dan modul B-02/B-03 diperbarui ke baseline baru; isi persyaratan tidak berubah) |
| Riwayat | v1.0 (2026-10-17) mendefinisikan produk ini sebagai "Lentera — kepemilikan penuh, single-business, self-hosted". Keputusan pemilik proyek 2026-10-29 mengubah arah produk menjadi SaaS multi-tenant bernama Artesis; v2.0 ini menggantikan v1.0 secara penuh. Ketentuan v1.0 yang bertentangan (termasuk "SaaS multi-tenant" sebagai non-goal) **tidak berlaku lagi**. |

**Konvensi status** (dipakai di seluruh dokumen):
- **Implemented** — terbukti pada kode di baseline + tercakup test.
- **Partial** — sebagian terbangun; sisa kekurangannya disebut eksplisit.
- **Planned** — belum ada di kode; direncanakan di Roadmap.
- **Proposed** — usulan yang belum diputuskan pemilik proyek.

---

## 1. Product Overview

### 1.1 Visi

Satu platform operasional optik yang lengkap — setara sistem mahal — yang dapat dipakai banyak perusahaan optik secara berlangganan, dengan data tiap perusahaan terisolasi, dan tetap dapat dipasang individual bagi bisnis yang membutuhkannya.

### 1.2 Permasalahan yang diselesaikan

- Toko optik kecil–menengah menjalankan penjualan, resep, stok lensa berkekuatan, proses lab, dan piutang secara manual atau tersebar — rawan selisih stok, kehilangan riwayat resep, dan laporan yang tidak rekonsiliasi.
- Sistem optik lengkap yang ada di pasar berharga tinggi per cabang dan mengunci fitur per tier mahal.
- Pemilik multi-cabang membutuhkan pagar akses per cabang yang ditegakkan di server, bukan sekadar tampilan.

### 1.3 Target pengguna

- **Segmen SaaS:** optik skala **kecil dan menengah** (keputusan pemilik proyek 2026-10-29) — satu sampai beberapa cabang, staf sedikit sampai belasan.
- **Segmen individual:** optik **besar** diprioritaskan ke penawaran instalasi individual (server & domain sendiri, mode tanpa paket dari basis kode yang sama).
- Pasar awal Indonesia; antarmuka empat bahasa (Indonesia, Inggris, Melayu, Mandarin) untuk promosi yang lebih luas.

### 1.4 Value proposition

- Kedalaman domain optik yang nyata (resep, precal, QC faset, rantai PR→PO→penerimaan→pembayaran kolektif, FIFO per cabang, akuntansi double-entry) pada harga langganan di bawah pesaing terdekat.
- Penegakan paket di server: fitur dan batas yang dibeli tenant adalah yang benar-benar berlaku.
- Data bisnis tenant tidak tercampur tenant lain — sebagai persyaratan produk (Bagian 6), dengan status pemenuhannya dinyatakan jujur per area.

### 1.5 Model bisnis SaaS

- Satu platform, banyak tenant. Setiap tenant = satu perusahaan optik dengan Owner, pengguna operasional, dan satu/lebih cabang sesuai Plan.
- Plan bertingkat (ketetapan pemilik proyek 2026-10-29):

| Plan | Harga | Batas kunci | Fitur pembeda |
|---|---|---|---|
| Free | Rp0 | maks 500 produk, 1 akun, 1 cabang, riwayat laporan 90 hari | Kasir lengkap + resep, laporan dasar |
| Solo | Rp49 rb/bln | produk tanpa batas, 2 akun | Laporan penuh + ekspor; tanpa peran lanjutan/CRM/PR-PO penuh |
| Team | Rp129 rb/bln | 7 akun (tambahan Rp19 rb/akun/bln) | Peran & izin, CRM/member, PR/PO penuh |
| Best Offer | Rp199 rb/bln + Rp99 rb/cabang berikutnya | multi-cabang | Akuntansi penuh, WhatsApp, API, Asisten AI |

- Penagihan saat ini: transfer manual + konfirmasi Platform Superadmin. Gateway pembayaran = **Proposed** (keputusan terpisah).
- Fitur per Plan ditegakkan `PlanGate` dari registry fitur (8 fitur boolean + 4 batas angka); Platform Superadmin dapat mengubah definisi Plan dan memberi override per tenant beralasan & teraudit.

### 1.6 Batasan produk (disengaja)

- Bukan marketplace, bukan aplikasi mobile native, bukan sistem untuk bisnis non-optik.
- Tidak ada penghapusan data transaksional: master → aktif/nonaktif; transaksional → cancel/void; koreksi keuangan → jurnal balik.
- Platform Superadmin bukan pelaku bisnis tenant (Bagian 2).

## 2. Jenis Pengguna & Peran

| Aktor | Definisi | Akses | Status |
|---|---|---|---|
| **Platform Superadmin** | Aktor platform (`users.is_platform`), terpisah dari semua tenant | Kelola tenant, Plan, Subscription, Billing, override fitur. **Ditolak (403)** pada seluruh aksi bisnis tenant — terbukti di test matriks keamanan | Implemented |
| **Tenant Owner** | Pemilik perusahaan optik; peran `Owner` terikat satu tenant | Seluruh izin bisnis di tenant-nya; '*' dalam tenant sendiri. Mengelola Paket Saya & permintaan penagihan | Implemented |
| **Tenant Admin** | Peran `Admin` | Operasional luas sesuai matriks izin; bukan pemilik langganan kecuali diberi `settings.manage` | Implemented |
| **Branch Manager** | Bukan peran tersendiri di kode — fungsi ini dipikul peran `Admin`/`Cabang` yang diberi cabang kelolaan lewat `user_branch` dan izin terkait | Sesuai izin + cabang kelolaan (`BranchContext`) | Implemented (sebagai pemetaan, bukan peran baru) |
| **Cashier** | Peran `Kasir` | Penjualan, pembayaran, penjualan di cabang operasi | Implemented |
| **Warehouse Staff** | Peran `Gudang` | Penerimaan, transfer, opname, PR | Implemented |
| **Peran operasional lain** | `Cabang` (staf cabang), `Refraksionis` (resep/pemeriksaan), `Teknisi` (lab & QC) | Sesuai matriks izin Blueprint Bagian C | Implemented |

Aturan aktor yang mengikat:
- Izin kanonis hanya dari registry (`config/permissions.php` + `PermissionRegistry`); string di luar registry = bug.
- Peran bawaan ter-seed global sebagai templat; Owner tenant dapat mengubah matriks peran lewat UI Peran bila Plan-nya membuka `roles_permissions`. **Status sesudah S-03 (Fase 0):** role kustom adalah milik tenant (`roles.tenant_id`); role bawaan tetap baris templat global yang dirujuk pengguna dan tidak dapat diubah tenant; settings per tenant. Lihat Bagian 6 baris "User dan role".
- Aktor platform tidak menerima satu pun izin bisnis dari matriks peran.

## 3. Prinsip Produk & Invarian Domain (tidak berubah oleh model SaaS)

1. **Otorisasi di batas server** — route middleware, policy, service, dan pagar query; menyembunyikan UI bukan otorisasi.
2. **PDR/PDL hanya di resep** — precal adalah input manual 11 parameter per item Lensa RX yang terikat frame; PDR/PDL tidak pernah muncul di precal (struktur data, tampilan, cetakan).
3. **Siklus hidup tanpa hapus** — master aktif/nonaktif; transaksional cancel/void; keuangan dikoreksi dengan jurnal balik.
4. **Integritas uang & stok** — transaksi DB, kunci baris pada titik rawan, anti tercatat-sebagian (pembayaran kolektif), saldo stok = ledger gerakan, jurnal selalu Σdebit = Σkredit. Setiap perubahan uang/stok wajib lulus regresi penuh + suite konkurensi 6 skenario di MySQL nyata.
5. **Audit-first** — mutasi sensitif tercatat di audit log (siapa/apa/kapan/cabang; target: + tenant, lihat Bagian 6).
6. **Empat bahasa** — semua teks UI lewat kunci lokalisasi `lang/id|en|ms|zh`; string keras = cacat.

## 4. Functional Requirements

Format per kelompok: deskripsi · aktor · aturan bisnis kunci · status + bukti. Acceptance criteria terukur dikonsolidasikan di Bagian 7.

### 4.1 Tenant Registration & Onboarding
- Publik mendaftar di `/daftar` (tamu; `throttle:6,1`): satu transaksi membuat Tenant (slug unik berurutan), cabang pertama, Tenant Owner (peran Owner, terikat tenant + cabang), dan Subscription Free. Kata sandi mengikuti `Password::defaults()` + konfirmasi; email unik di `users`. Sesudah daftar, pengguna langsung login ke dashboard.
- Platform Superadmin juga dapat menyediakan tenant dari panel (memakai layanan yang sama); kata sandi awal Owner ditampilkan **satu kali** untuk diserahkan ke pemilik tenant.
- **Status: Implemented** (`TenantSignupService`, `SignupController`, `TenantController::store`; test `SaasSignupBillingTest`, `TenantProvisioningTest`). **Kelanjutan:** verifikasi email diterapkan di B-01 (pemilik baru menerima email verifikasi; sampai terverifikasi aksesnya terbatas per pengguna — hanya halaman verifikasi, kirim ulang ber-throttle, dan keluar — sedangkan CAPTCHA Turnstile diterapkan di S-05.

### 4.2 Tenant Provisioning
- Provisioning = pembuatan tenant utuh (tenant + cabang + owner + langganan Free) lewat satu layanan yang dipakai pendaftaran mandiri maupun panel platform — tidak ada jalur provisioning kedua yang menyimpang.
- **Status: Implemented** (satu `TenantSignupService::register`).

### 4.3 Branch Management
- Tenant mengelola cabangnya (buat/nonaktif); cabang baru menstempel `tenant_id` tenant aktor dan digerbang batas `branches_max` Plan. Cabang dengan proses menggantung tidak dapat dinonaktifkan (daftar penghalang ditampilkan). Aktor hanya melihat cabang tenant-nya pada pengelolaan pengguna & pilihan cabang.
- **Status: Implemented** — pengelolaan dalam tenant terpagar konteks aktor; pagar tenant pada data bercabang ditegakkan Fase 0 (PR #29).

### 4.4 User Management
- Tenant Owner/Admin berizin mengelola pengguna **tenant-nya sendiri**: daftar, buat (digerbang `users_max`), ubah, alih aktif, tetapkan peran & cabang kelolaan. Aktor platform tidak tampil dan tidak terjangkau dari pengelolaan pengguna tenant; validasi cabang dibatasi tenant aktor; `UserService` menyaring sinkronisasi cabang per tenant aktor sebagai lapis kedua.
- Pengguna tidak dapat menonaktifkan dirinya / mengubah peran dirinya; perubahan izin berlaku request berikutnya & teraudit.
- **Status: Implemented** (perbaikan isolasi 2026-10-30; test `UserTenantIsolationTest` 7 test).

### 4.5 Role & Permission
- Registry izin kanonis; matriks peran dapat diubah Owner (Plan Team+: `roles_permissions` digerbang middleware `plan.feature` pada edit/update peran). Hak platform (`plans.manage`, `tenants.manage`) hanya untuk aktor platform.
- **Status: Implemented** — penegakan izin Implemented; kepemilikan peran diselesaikan S-03 (Fase 0, PR #27): role kustom milik tenant, role bawaan = templat global baca-saja yang dirujuk (mengubah peran kustom tenant A tidak memengaruhi tenant B — teruji lintas tenant).

### 4.6 Product / Catalog Management
- Katalog dua tingkat: induk (`product_parents`) → varian (`products` = satu-satunya unit stok; SKU, barcode, harga beli/jual per varian; gambar per varian). Lima tipe tetap (frame, lensa, lensa RX, softlens, aksesori) & lima kategori tetap tanpa UI kelola. Pembuatan produk tenant menstempel `tenant_id` dan digerbang `products_max`.
- **Status: Implemented** — fungsi katalog Implemented; isolasi katalog per tenant selesai pada S-02 (Fase 0, PR #28) mengikuti keputusan OD-2 (10 Nov 2026): katalog hanya terlihat/dipakai tenant pemiliknya; ADR-012 berlaku **di dalam tenant** (lintas cabang satu tenant), bukan lintas tenant.

### 4.7 Customer & Prescription Management
- Master pelanggan (WA duplikat = peringatan, bukan blokir; merge duplikat = operasi Owner beraudit) dan resep grid OD/OS (terkunci sesudah terpakai; AXIS wajib 0–180 bila CYL ≠ 0; resep harus milik pelanggan transaksi). Daftar resep global + pintu "Tambah Resep" lewat pilih pelanggan.
- **Status: Implemented** — fungsi Implemented; pelanggan & resep terisolasi per tenant sesudah S-02 (Fase 0, PR #28) mengikuti OD-2: global **di dalam tenant** (lintas cabang satu tenant, ADR-012 sebagaimana dikualifikasi OD-2), tidak pernah lintas tenant.

### 4.8 POS & Sales
- Terminal kasir satu halaman: keranjang, resep per pelanggan, klaim asuransi otomatis dari `claim_value` master, precal manual per item RX, pembayaran append-only multi-metode (tunai/debit/QRIS/transfer/kartu/poin/kredit toko), DP & status Lunas turunan, split payment, cicilan & piutang, diskon berambang izin, void ber-alasan dengan pembalikan penuh (stok, pembayaran, jurnal, komisi, poin), retur (resale/damaged), garansi per item penjualan.
- Cabang transaksi = cabang operasi aktor (atau `branch_id` eksplisit yang aktor berhak atasnya) — tidak pernah cabang asal pelanggan.
- **Status: Implemented** (termasuk gerbang Plan untuk ekspor laporan & fitur terkait). Isolasi tenant pada data penjualan ditegakkan pagar cabang + tenant (Fase 0).

### 4.9 Inventory
- `stock_movements` single-writer lewat `InventoryService`; saldo = gerakan terakhir per (produk, cabang); costing FIFO per cabang dengan lapisan penerimaan & penangguhan backorder; transfer antar cabang (kirim/terima idempoten; stok dalam perjalanan terlihat); opname (snapshot → hitung → review → approval menerbitkan selisih); impor stok awal (preview → commit, policy eksplisit peran tertinggi, bukan aktor platform); expiry softlens memblokir penjualan kedaluwarsa; label barcode.
- **Status: Implemented.** Pagar tenant eksplisit ditegakkan Fase 0, termasuk partisi gudang garansi per tenant (B-02).

### 4.10 Purchasing
- PR (draft→submitted→approved→converted; gabung se-supplier; PR otomatis dari penjualan: RX selalu, stok hanya kekurangannya) → PO → penerimaan bertahap (melebihi sisa ditolak kecuali berizin + alasan) → pembayaran tunggal & **kolektif FIFO** (validasi penuh sebelum transaksi; kegagalan di tengah = nol baris tertinggal) → retur pembelian (mengurangi tagihan / menjadi kredit supplier). Digerbang Plan `purchasing_full` untuk tenant.
- **Status: Implemented** (kontrak anti-sebagian diuji injeksi kegagalan).

### 4.11 Stock Transfer
- Transfer dari detail PO atau stok cabang; sisa kirim terhitung (diterima − terkirim); penerimaan di cabang tujuan; pembatalan mengembalikan stok asal lewat gerakan penyeimbang.
- **Status: Implemented.**

### 4.12 Reporting
- Laporan penjualan, pembelian (bruto/retur/neto), stok, utang supplier, piutang, pemakaian asuransi, analitik supplier & penjualan, laporan keuangan — selalu menerapkan pagar aktor; ekspor (CSV/XLSX) memakai filter yang sama persis dengan tampilan (diuji kesetaraan total). Untuk tenant: riwayat laporan diklem ke `report_history_days` Plan dan ekspor digerbang `reports_advanced`.
- **Status: Implemented.**

### 4.13 Settings
- Pengaturan umum (identitas toko, PPN, kertas) lewat allowlist kunci; kredensial gateway WA & AI dari UI Pengaturan, terenkripsi, tersamar.
- **Status: Implemented** — settings per tenant selesai pada S-03 (Fase 0, PR #27): baris pengaturan bertenant hanya berlaku bagi tenant pemiliknya; baris global instalasi (tenant_id NULL) tetap ada untuk konfigurasi tingkat instalasi (mis. gateway platform, penagihan).

### 4.14 Notification & WhatsApp
- Gateway Meta Cloud API langsung di belakang antarmuka yang dapat diganti penyedia; pengiriman async antrean (coba lagi 3×) dan tidak pernah menggagalkan transaksi pemicu. Invoice, pengingat pengambilan, after-service H+7, cicilan, ulang tahun; inbox dua arah; kampanye bersegmen wajib persetujuan; opt-out dihormati. Untuk tenant, pengiriman otomasi digerbang Plan `whatsapp` per tenant cabang terkait.
- **Status: Partial** — fungsi Implemented; **satu gateway per instalasi** (kredensial & nomor pengirim belum per tenant). Gateway per tenant = Roadmap Fase 3.

### 4.15 Subscription & Billing
- Platform menetapkan Plan ke tenant (`assignPlan`: langganan lama ditutup `expired`, baru dibuat, teraudit); langganan kedaluwarsa → tenant kembali ke hak Free. Tenant mengajukan permintaan penagihan dari Paket Saya (paket berbayar; 1/3/6/12 bulan; jumlah dibekukan = harga + cabang tambahan × bulan; satu pending per tenant); Platform mengonfirmasi (langganan aktif, masa berlaku mulai hari konfirmasi) atau menolak (tanpa perubahan). Tujuan transfer diatur Platform di panel Penagihan.
- **Status: Implemented** (`PlanService`, `BillingService`; test `SaasSignupBillingTest`). **Planned/Proposed:** gateway pembayaran, penagihan akun tambahan Team otomatis per akun, penumpukan sisa masa langganan saat konfirmasi.

### 4.16 Platform Administration
- Panel khusus aktor platform (middleware `platform` + izin `plans.manage`/`tenants.manage`): kelola Plan & fitur, kelola tenant (termasuk alih `is_active` dan provisioning), override fitur per tenant (alasan wajib, kedaluwarsa, teraudit), penagihan & pengaturan rekening, halaman Paket Saya untuk tenant.
- **Status: Implemented** — termasuk penegakan `tenants.is_active` (S-01 Fase 0): pengguna tenant nonaktif ditolak pada login dan akses sampai tenant diaktifkan kembali.

### 4.17 Backup & Data Export
- Backup snapshot **seluruh database instalasi** (manual & terjadwal, terverifikasi, retensi 14) + restore dengan backup keselamatan `pra-restore` — khusus aktor platform (atau Owner pada instalasi individual sejati), digerbang izin `backups.manage` + `BackupPolicy` berbasis mode; aktor tenant selalu ditolak.
- **Ekspor data per tenant (B-03, PR #35): Implemented** — Tenant Owner (atau platform atas namanya) mengunduh salinan lengkap data tenantnya sendiri (ZIP berisi backup.json tersaring per kepemilikan; registry klasifikasi tabel eksplisit yang diuji kelengkapannya; kolom kredensial dikecualikan; unduhan teraudit).
- **Restore per tenant: diputuskan TIDAK dibangun** (keputusan pemilik proyek 11 Nov 2026) — restore tetap whole-DB tingkat platform. **Offsite backup: belum terimplementasi** (sakelar + peringatan audit; menunggu kredensial penyimpanan eksternal pemilik instalasi).

## 5. Non-Functional Requirements

| Area | Persyaratan | Status di baseline |
|---|---|---|
| Tenant isolation | Tenant A tidak dapat membaca/mengubah/mengekspor data tenant B lewat jalur apa pun (Bagian 6) | **Implemented** — Fase 0 selesai (PR #23–#35, s.d. `43db5b54`); awalan cache ditutup di C-01; sisa pengerasan kapasitas terukur di Fase 6 |
| Authentication | Sesi Laravel; kata sandi `Password::defaults()`; pemulihan istimewa hanya Owner aktif (respons generik anti-enumerasi, teraudit, throttle) | Implemented |
| Authorization | Registry izin + policy + pagar konteks; aktor platform ditolak dari bisnis | Implemented (matriks keamanan per modul) |
| Data privacy | Kredensial terenkripsi & tersamar; audit log menyaring kolom rahasia; portal member hanya data token itu | Implemented |
| Audit logging | Mutasi sensitif tercatat (aktor, aksi, subjek, cabang, nilai lama/baru tersaring, IP) | Implemented; kolom tenant belum ada (Fase 3) |
| Availability | Scheduler + queue worker wajib hidup; dashboard memperingatkan job gagal & backup basi | Implemented (mekanisme peringatan) |
| Performance | Daftar 25/baris; laporan berat agregasi; grafik server-rendered tanpa pustaka luar | Implemented |
| Scalability | Satu instansi melayani banyak tenant kecil–menengah; angka kapasitas terverifikasi belum ada — wajib diukur sebelum klaim (Fase 6) | **Belum terukur** |
| Backup & DR | Backup harian terverifikasi + offsite + uji restore terdokumentasi | Implemented per instalasi; DR platform = prosedur runbook |
| Observability | Log Laravel, `failed_jobs`, lencana status backup, audit log | Implemented dasar |
| Maintainability | Monolit modular `app/Modules/*`; kontrak Blueprint; CI 5 job; branch protection | Implemented |
| Data retention | Backup retensi 14 hari; audit log hanya-INSERT dari aplikasi | Implemented |
| Secure offboarding | Tenant dapat dinonaktifkan & datanya diekspor lalu dihapus terjadwal atas persetujuan pemilik proyek | **Partial** — penonaktifan (S-01) dan ekspor per tenant (B-03) Implemented; penghapusan terjadwal **Planned** — menunggu keputusan retensi OD-5 |

## 6. Tenant Isolation Requirements

**Persyaratan mutlak:** tenant A tidak boleh membaca, mengubah, menghapus, mengekspor, atau memengaruhi data tenant B — lewat URL langsung, query, ekspor, berkas, cache, antrean, maupun backup. Tabel berikut menyatakan cakupan wajib **dan status terbukti di baseline** (status jujur adalah bagian dari persyaratan dokumen ini):

| Area | Persyaratan | Status baseline |
|---|---|---|
| Direct object IDOR | Setiap rute detail/aksi memvalidasi kepemilikan tenant objek, bukan hanya keberadaan izin | **Implemented** — validasi ember di lapisan layanan + policy (PR #29–#33); matriks test keamanan per modul |
| Query listing & detail | Semua daftar/detail terpagar tenant aktor secara default (scope), bukan filter manual per layar | **Implemented** — `TenantScope`/`BranchScope` + pagar layanan; batas kepercayaan baca in-process diatur ADR-022 |
| Create/update/delete | Penulisan menstempel & memvalidasi tenant aktor; objek tenant lain tidak dapat diubah/dinonaktifkan | **Implemented** — stempel + validasi ember layanan; tulis global tanpa aktor wajib konteks sistem (ADR-022) |
| Branch switching | Aktor hanya dapat beroperasi di cabang tenant-nya; `branch_id` dari request tidak pernah dipercaya | **Implemented** — `BranchContext` membedakan cabang kelolaan vs cabang perusahaan (PR #29) |
| Customer & prescription | Pelanggan & resep hanya terlihat/dipakai tenant pemiliknya | **Implemented** (S-02) — global di dalam tenant sesuai OD-2/ADR-012 |
| Product & product parent | Katalog hanya terlihat/dipakai tenant pemiliknya | **Implemented** (S-02) |
| Sales & invoice | Penjualan, pembayaran, cetakan hanya tenant pemilik | **Implemented** — pagar cabang + tenant (PR #29–#33) |
| Inventory & stock transfer | Gerakan, lapisan FIFO, transfer, opname hanya dalam tenant | **Implemented** — termasuk partisi gudang garansi per tenant (B-02, PR #34) |
| Purchase order | Rantai PR/PO/receipt/pembayaran hanya dalam tenant | **Implemented** |
| Supplier & master data | Supplier/asuransi/dokter milik tenant tidak terlihat tenant lain | **Implemented** (S-02 + PR #30) |
| User & role | Pengelolaan pengguna terisolasi per tenant; definisi peran per tenant | **Implemented** — pengguna (2026-10-30) + peran kustom per tenant & settings per tenant (S-03, PR #27); peran bawaan = templat global dirujuk |
| Settings | Pengaturan per tenant | **Implemented** (S-03) |
| WhatsApp gateway & inbox | Gateway, template, inbox, kampanye per tenant | **Belum** — satu gateway per instalasi (keputusan OD-4 terbuka); pagar ember pada webhook/inbox sudah ditegakkan (PR #33) |
| Audit log | Log membawa identitas tenant; pembacaan terpagar tenant | **Partial** — log membawa `branch_id` (cabang selalu milik satu tenant) dan pembacaan terpagar cabang/konteks; kolom `tenant_id` khusus belum ada |
| Report & export | Laporan & ekspor hanya agregat tenant aktor | **Implemented** — pagar cabang/tenant + izin ekspor; ekspor data penuh per tenant di B-03 |
| Backup & restore | Tenant tidak pernah dapat mengakses snapshot global; ekspor/restore per tenant terverifikasi kepemilikan | **Implemented untuk backup & ekspor** — snapshot global khusus platform (`backups.manage` + BackupPolicy); ekspor per tenant B-03. Restore per tenant diputuskan tidak dibangun |
| File storage | Berkas (gambar produk, dokumen) terpisah per tenant & tidak dapat ditebak/diakses silang | **Partial** — arsip ekspor per direktori tenant (B-03); berkas unggahan lain masih disk bersama per instalasi |
| Cache | Kunci cache tidak bertabrakan/terbaca antar-tenant | **Implemented** — `TenantCache` memberi awalan ember pemilik (`t{tenant_id}:`, standalone/platform/system) pada cache data bisnis (C-01); tanpa awalan hanya data platform statis |
| Queue & scheduler | Job membawa konteks tenant; scheduler tidak mencampur data antar-tenant | **Implemented** — perintah terjadwal berjalan dalam konteks sistem eksplisit; layanan berparameter aktor memvalidasi ember (ADR-022) |
| Tenant suspension | Tenant nonaktif: seluruh penggunanya ditolak login & akses sampai diaktifkan kembali | **Implemented** (S-01) |
| Tenant deletion/offboarding | Ekspor penuh per tenant → penghapusan terjadwal terverifikasi, tanpa menyentuh tenant lain | **Partial** — ekspor penuh ada (B-03); penghapusan terjadwal belum (OD-5) |

Pemenuhan tabel ini adalah isi Roadmap Fase 1–5. Tidak ada baris yang boleh dinyatakan selesai tanpa test negatif antar-tenant yang dikutip.

## 7. Acceptance Criteria (terukur)

Kriteria keamanan & isolasi (skenario positif + negatif wajib berpasangan):

- **AC-ISO-01** — Diberikan dua tenant A dan B dalam satu instansi: pengguna A yang membuka URL detail pelanggan/resep/produk/penjualan/PO milik B menerima 403/404, dan daftar A tidak memuat satu pun baris B (diuji per area Bagian 6 saat fasenya dikerjakan).
- **AC-ISO-02** — Pengguna tenant yang dinonaktifkan platform (`is_active = false`) ditolak pada login berikutnya dan pada request berjalan (sesi aktif terputus). *(Sudah terpenuhi — S-01 Fase 0.)*
- **AC-ISO-03** — Tenant Owner yang memanggil rute backup unduh/pulihkan pada instansi bersama ditolak; hanya aktor platform yang dapat menjalankan backup tingkat instalasi. *(Sudah terpenuhi — gerbang `backups.manage` + BackupPolicy; ekspor per tenant diatur B-03.)*
- **AC-PLAT-01** — Aktor platform yang membuka rute bisnis apa pun (penjualan, pembelian, stok, laporan tenant) menerima 403. *(Sudah terpenuhi — test matriks keamanan.)*
- **AC-PLAN-01** — Tenant Free yang membuka fitur Team (mis. peran & izin) menerima 403 di server pada GET maupun POST; sesudah override platform beralasan, fitur terbuka sampai kedaluwarsa override. *(Sudah terpenuhi — `SaasEnforcementTest`.)*
- **AC-PLAN-02** — Tenant yang mencapai `products_max` ditolak membuat produk ke-501 dengan pesan validasi pada field, bukan 500. *(Sudah terpenuhi.)*
- **AC-BILL-01** — Permintaan penagihan kedua saat satu pending ada ditolak; konfirmasi platform mengaktifkan langganan dengan `expires_at` = hari konfirmasi + jumlah bulan; penolakan tidak mengubah langganan. *(Sudah terpenuhi — `SaasSignupBillingTest`.)*
- **AC-SIGNUP-01** — Pendaftaran dengan email yang sudah terdaftar ditolak; pendaftaran sah menghasilkan tepat satu tenant, satu cabang, satu Owner, satu langganan Free dalam satu transaksi (kegagalan di tengah = nol baris). *(Sudah terpenuhi.)*
- **AC-USER-01** — Admin tenant A tidak melihat/mengubah pengguna tenant B maupun aktor platform pada daftar, detail, cetak, dan aksi alih-aktif. *(Sudah terpenuhi — `UserTenantIsolationTest`.)*

Kriteria bisnis inti (warisan gerbang yang sudah lulus dan tetap mengikat):

- **AC-SALES-01** — Void penjualan yang proses lensanya sudah `done` ditolak; void yang sah membalik stok, pembayaran, jurnal, komisi, dan poin secara utuh (rekonsiliasi test).
- **AC-PUR-01** — Pembayaran kolektif yang digagalkan di tengah alokasi meninggalkan nol baris pembayaran/alokasi/jurnal (test injeksi kegagalan).
- **AC-CONC-01** — Enam skenario konkurensi tetap (submit ganda, bayar bersamaan, stok terakhir, kolektif ganda, Complete ganda, penerimaan bersamaan) lulus di MySQL nyata pada setiap perubahan uang/stok.
- **AC-PRECAL-01** — Payload `rx_parameters` tersimpan tidak memuat kunci PDR/PDL dalam keadaan apa pun (pemindaian otomatis).

## 8. Di Luar Cakupan (Non-Goals)

- Mengubah repositori/produksi `toromagetan/Artesis` dari proyek ini.
- Menyalin data produksi sistem lama ke SaaS tanpa proyek migrasi tersendiri yang disetujui.
- Menjalankan mode satu instansi bersama di produksi sebelum Fase isolasi yang relevan di Roadmap dinyatakan lulus.
- Aplikasi mobile native; marketplace; multi-bahasa di luar empat bahasa yang ditetapkan.

## 9. Keputusan Terbuka (OPEN DECISIONS — milik pemilik proyek)

1. **OD-1 Topologi produksi:** satu instalasi per tenant di server bersama **vs** satu instansi multi-tenant penuh. Menentukan urgensi Fase 1–3 Roadmap.
2. **OD-2 Nasib ADR-012 di mode SaaS:** — **DIPUTUSKAN 10 Nov 2026: isolasi per tenant penuh.** Seluruh data bisnis (termasuk katalog dan master) adalah milik tenant; ADR-012 hanya berlaku **di dalam tenant** (master global lintas cabang satu tenant), tidak pernah lintas tenant. Keputusan ini menutup pertanyaan topologi untuk kepemilikan data pada kedua pilihan OD-1.
3. **OD-3 Gateway pembayaran** untuk Billing (tetap manual vs Midtrans/Xendit).
4. **OD-4 Kredensial WhatsApp per tenant** (nomor pengirim per tenant vs satu nomor platform) — menentukan desain Fase 3.
5. **OD-5 Retensi & penghapusan data tenant offboarding** (masa tenggang, bukti penghapusan).
6. **OD-6 Tinjauan penutur asli Melayu/Mandarin** sebelum promosi pasar tersebut.

## 10. Dokumen terkait

- [PRD-DETAIL.md](PRD-DETAIL.md) — spesifikasi perilaku per menu. Identitas & framingnya diselaraskan pada D-01 (2026-11-12); perilaku per menu di dalamnya tetap acuan — perubahan perilaku hanya lewat persetujuan pemilik proyek.
- [BLUEPRINT.md](BLUEPRINT.md) — arsitektur aktual & target, klasifikasi data, keamanan, status ADR.
- [ROADMAP.md](ROADMAP.md) — fase pemenuhan persyaratan dokumen ini.
