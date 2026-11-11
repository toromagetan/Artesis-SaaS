# BLUEPRINT v3.0 — ARTESIS: Arsitektur SaaS Multi-Tenant & Kontrak Rekayasa

| | |
|---|---|
| Dokumen | Blueprint teknis — v3.0 (penyelarasan SaaS dari v2.0, 2026-10-30) |
| Produk | **Artesis** — SaaS POS Optik & Operational Management (multi-tenant); basis kode yang sama juga berjalan sebagai instalasi individual |
| Baseline audit | `main` @ `43db5b54` (2026-11-12), repositori `toromagetan/Artesis-SaaS` — diselaraskan D-01: status Bagian J dan ADR-012/020 diperbarui ke keadaan sesudah Fase 0 (PR #23–#35) |
| Status | Bagian A–H = kontrak rekayasa yang **tetap berlaku** (terbangun & teruji untuk konteks bisnis optik). **Bagian J = arsitektur SaaS**: keadaan aktual yang terbukti di baseline + arsitektur target tenant-aware yang belum dibangun (pemenuhannya diatur `docs/ROADMAP.md`). Status tiap ADR dinyatakan eksplisit di Bagian I. |
| Hirarki dokumen | PRD (`docs/PRD.md`) = apa & mengapa · PRD Detail (`docs/PRD-DETAIL.md`) = perilaku per menu · **Blueprint ini = bagaimana dibangun, sampai tingkat yang dapat dieksekusi tanpa menebak** |
| Konvensi | "WAJIB" = pelanggaran = gagal gerbang. "BAWAAN" = nilai awal yang dapat dikonfigurasi owner. Semua nama tabel/kolom/izin/rute dalam dokumen ini adalah **kontrak** — implementasi tidak boleh menyimpang tanpa revisi dokumen ber-commit. |

---

# BAGIAN A — FONDASI TEKNIS

## A.1 Tumpukan & Versi Terkunci (keputusan)

| Lapisan | Pilihan terkunci | Catatan |
|---|---|---|
| Bahasa | PHP 8.3+ (didukung s.d. 8.5) | Dinyatakan di `composer.json` (`"php": "^8.3"`) |
| Framework | Laravel 13.x | Mayor terbaru (rilis 17 Maret 2026; perbaikan bug s.d. Q3 2027, keamanan s.d. 17 Maret 2028); rilis stabil 13.x terbaru saat Fase 0 dimulai, dikunci di `composer.lock`. *Direvisi dari Laravel 12 atas keputusan owner 2026-10-17.* |
| Database | MySQL 8.0 (InnoDB, utf8mb4) | Satu-satunya DB yang didukung |
| Frontend | Blade + Alpine.js (POS terminal Alpine; tanpa Livewire) | Tanpa SPA; tanpa build-step wajib untuk halaman biasa |
| CSS | Tailwind (build lokal, di-commit hasilnya tidak — lewat pipeline CI) | Palet emerald + emas sebagai token desain |
| Antrean | Database queue (tanpa Redis) | Kesederhanaan operasional per instalasi; konteks tenant pada job adalah target Fase 1 Roadmap (Bagian J.2) |
| Penyimpanan berkas | Disk lokal terstruktur `storage/app/documents` | Dokumen hasil generate (PDF) & unggahan |
| PDF/Cetak | Dokumen cetak = view Blade khusus cetak + CSS print (tanpa pustaka PDF) | Ukuran A5/A4 + thermal 58/80mm |
| Ekspor | XlsxWriter OOXML mandiri (tanpa pustaka luar, ADR-016 M5) + CSV native | Menghormati filter & pagar cabang |

**Ekstensi PHP WAJIB (daftar tertutup):** `ext-bcmath` **SECARA EKSPLISIT TIDAK DIPAKAI DAN TIDAK BOLEH DIPANGGIL** (pelajaran insiden produksi Artesis: fungsi bc* tidak ada di server → 500). Daftar ekstensi yang BOLEH dipakai dan WAJIB dinyatakan satu per satu di `composer.json` require: `ext-mbstring, ext-intl, ext-gd, ext-zip, ext-pdo_mysql, ext-xml, ext-curl, ext-fileinfo, ext-openssl, ext-json`. Semua perhitungan uang memakai helper keuangan internal (Bagian A.5), bukan ekstensi aritmetika presisi apa pun. CI menjalankan pemindai statis yang **menolak build** bila menemukan pemanggilan `bc*` atau fungsi ekstensi di luar daftar (Bagian G.2).

## A.2 Arsitektur Berlapis

Setiap permintaan mengalir satu arah: **Route → Middleware (auth, konteks cabang) → Controller (tipis) → FormRequest (validasi) → Policy (otorisasi sumber daya) → Service (aturan bisnis, transaksi) → Model/Repository (query terpagar) → Event (efek lintas modul)**.

- Controller WAJIB tipis: tidak ada query bisnis, tidak ada perhitungan uang/stok di controller.
- Service adalah pemilik aturan bisnis & batas transaksi DB; satu operasi bisnis = satu method service publik.
- Model hanya relasi, scope, accessor terhitung (mis. durasi proses lensa) — bukan tempat aturan bisnis lintas model.
- Event domain adalah satu-satunya jembatan efek lintas modul (jurnal, notifikasi, poin).

## A.3 Aturan Modul (keras, dapat diuji statis)

Modul (14, sama seperti Blueprint v1): Identity, Master, Catalog, CRM, Clinical, Sales, Lab, Purchasing, Inventory, Accounting, Membership, Reporting, Comms, Platform.

1. Modul hanya menulis tabel miliknya. Pelanggaran dicek di review + uji arsitektur (daftar tabel per modul di Bagian B.0).
2. Accounting tidak pernah di-UPDATE modul lain; ia mengonsumsi event lewat listener posting (Bagian E.1).
3. Inventory adalah satu-satunya penulis `stock_movements`; semua modul meminta pergerakan lewat `InventoryService`.
4. Reporting hanya SELECT.
5. `branch_id` pada query bisnis WAJIB berasal dari konteks aktor (service `BranchContext`), tidak pernah dari input request mentah.
6. Tidak ada logika bisnis di Blade; Blade hanya menampilkan.
7. **Konteks tenant (mode SaaS):** identitas tenant aktor WAJIB diturunkan dari `users.tenant_id` di sisi server — tidak pernah dari input request. Aktor tanpa tenant adalah instalasi individual: gerbang paket tidak berlaku (Bagian J.1). Penulisan entitas milik tenant (cabang, pengguna, produk) WAJIB menstempel `tenant_id` aktor.

## A.4 Konvensi Penamaan (kontrak)

- Tabel: snake_case jamak (`purchase_orders`). Kolom: snake_case; uang berakhiran `_amount` atau nama baku (`total_amount, paid_amount, remaining_amount`); waktu: `*_at`; status: `status` bertipe enum string.
- Rute: `kebab-case` per modul, nama rute `modul.sumber.aksi` (mis. `purchasing.orders.show`).
- Izin: `modul.aksi` (Bagian C) — string izin WAJIB terdaftar di `config/permissions.php` + `PermissionRegistry`; string di luar registry = bug.
- Event: kata kerja lampau (`SaleCompleted`, `ReceiptStored`, `CollectivePaymentPosted`).
- Service: `<Modul>Service` dengan method kata kerja (`createSale`, `postCollectivePayment`).
- Nomor dokumen: Bagian E.4.

## A.5 Helper Keuangan (satu-satunya jalan aritmetika uang)

`Money` helper internal (bukan ekstensi): representasi string desimal ter-normalisasi 2 digit; operasi `add, sub, mul(qty), cmp, isZero, isNegative` berbasis integer sen (rupiah × 100) untuk menghindari galat float; pembulatan **half-up** pada titik yang dispesifikasikan per operasi (total baris → total dokumen). Kolom DB `DECIMAL(15,2)`. Semua modul WAJIB lewat helper ini; pemakaian `float` mentah untuk uang = gagal review.

# BAGIAN B — KAMUS DATA (kontrak skema)

## B.0 Kepemilikan tabel per modul
Identity: `users, roles, permissions, role_permission, user_role, user_branch`. Master: `branches, suppliers, insurances, doctors, settings`. Catalog: `products, product_attributes`. CRM: `customers`. Clinical: `prescriptions`. Sales: `sales, sale_items, sale_payments, sale_installments, sale_returns, sale_return_items`. Lab: `lens_processes, qc_point_templates, qc_checks`. Purchasing: `purchase_requests, purchase_request_items, purchase_orders, purchase_order_items, receipts, receipt_items, purchase_payments, purchase_payment_allocations, purchase_returns, purchase_return_items`. Inventory: `stock_movements, stock_transfers, stock_transfer_items, stock_opnames, stock_opname_items`. Accounting: `accounts, journals, journal_lines, fixed_assets, period_locks, payrolls, commissions`. Membership: `membership_levels, points_ledger`. Comms: `wa_gateways, wa_templates, wa_messages, wa_campaigns`. Platform: `audit_logs, api_tokens, backups`.

Konvensi umum semua tabel: `id BIGINT UNSIGNED` PK; `created_at/updated_at TIMESTAMP`; tabel transaksional membawa `branch_id BIGINT UNSIGNED FK→branches` + indeks komposit `(branch_id, created_at)`; uang `DECIMAL(15,2)`; kuantitas `INT` bertanda hanya di `stock_movements.qty`.

**Tambahan SaaS (migrasi 000048–000050, 2026-10-29):** modul Platform memiliki tabelnya sendiri — `plans` (slug unik, `price_monthly`, `extra_branch_price`, `is_active`), `plan_features` (plan_id, `feature_key`, `feature_value` JSON — nilai efektif dari `App\Support\PlanFeatureRegistry`), `tenants` (name, `slug` unik, email, phone, `is_active`), `tenant_subscriptions` (tenant_id, plan_id, status `active`/`expired`, starts_at, expires_at NULL, created_by), `tenant_feature_overrides` (tenant_id, feature_key, value JSON, reason, expires_at, created_by), `tenant_billing_requests` (tenant_id, plan_id, extra_branches, months, `amount` dibekukan, status `pending`/`confirmed`/`rejected`, note, decided_by/at, subscription_id). Kolom `tenant_id BIGINT UNSIGNED NULL FK→tenants` ditambahkan ke **`branches`** dan **`users`** (NULL = instalasi individual / aktor platform), dan ke **`products`** (stempel kepemilikan untuk penghitungan kuota — BUKAN pagar baca; lihat Bagian J.3). Klasifikasi kepemilikan seluruh 90 tabel ada di Bagian J.3.

## B.1 Identity & Master
- **branches**: `name VARCHAR(100)` (tanpa kolom kode — keputusan terkunci), `address TEXT NULL`, `phone VARCHAR(30) NULL`, `is_active BOOL default 1`. Indeks: `name`.
- **users**: `name`, `username VARCHAR(50) UNIQUE`, `email VARCHAR(150) UNIQUE NULL`, `password` (hash), `phone NULL`, `locale VARCHAR(5) default 'id'` (preferensi bahasa — ADR-011), `is_active`, `is_platform BOOL default 0` (aktor teknis — bukan pelaku bisnis), `tenant_id FK NULL` (migrasi 000048; NULL = aktor instalasi individual atau aktor platform; akar konteks tenant, Bagian J), `last_login_at NULL`.
- **roles**: `name VARCHAR(50) UNIQUE` (Owner, Admin, Kasir, Gudang, Cabang, Refraksionis, Teknisi), `label`. **permissions**: `name VARCHAR(100) UNIQUE` (registry Bagian C), `group_name`. **role_permission** (role_id, permission_id) PK komposit. **user_role** (user_id, role_id). **user_branch** (user_id, branch_id) = cabang yang dikelola; kosong + peran Owner = akses global.
- **suppliers**: `name`, `contact_person NULL`, `phone NULL`, `email NULL`, `address NULL`, `is_active`. **insurances**: `name`, `phone NULL`, `is_active`. **doctors**: `name`, `clinic NULL`, `phone NULL`, `commission_rate DECIMAL(5,2) default 0` (%), `is_active`.
- **settings**: `key VARCHAR(100) UNIQUE`, `value TEXT NULL` — pasangan kunci-nilai untuk Pengaturan Umum (identitas toko, tarif PPN bawaan, mode PPN, ukuran kertas bawaan, format tanggal). Nilai yang bersifat rahasia (kredensial gateway) TIDAK di sini — lihat `wa_gateways`.

## B.2 Catalog, CRM, Clinical
- **products**: `sku VARCHAR(50) UNIQUE`, `type ENUM('frame','lens','rx','softlens','accessory')`, `name VARCHAR(150)`, `brand VARCHAR(80) NULL`, `color VARCHAR(50) NULL`, `cost_price DECIMAL(15,2)` (modal), `sale_price DECIMAL(15,2)`, `is_active`. Indeks: `(type, is_active)`, `name`.
- **product_attributes**: `product_id FK`, `key VARCHAR(60)`, `value VARCHAR(150)` — atribut per tipe: frame (`eye_size, bridge_size, temple_size`), lensa (`index, coating, diameter, lens_kind`), rx (`sph_min, sph_max, cyl_min, cyl_max`), softlens (`power, wear_period, expiry_tracked BOOL`). Indeks `(product_id, key)` unik.
- **customers**: `name`, `phone VARCHAR(30)` (nomor WA utama), `email NULL`, `address NULL`, `birth_date DATE NULL` (untuk otomasi ulang tahun), `branch_id FK` (cabang pendaftaran — atribut asal, BUKAN pagar), `insurance_id FK NULL`, `membership_level_id FK NULL`, `is_active`. Indeks: `phone`, `name`. **MASTER GLOBAL DI DALAM TENANT (ADR-012 sebagaimana dikualifikasi OD-2, diputuskan 10 Nov 2026): berlaku di semua cabang tenant yang sama; pagar antar-tenant adalah kepemilikan tenant (`customers.tenant_id` + `TenantScope`), pagar antar-cabang di dalam tenant adalah izin `customers.*`.**
- **prescriptions** (Clinical): `customer_id FK`, `branch_id FK` (cabang pemeriksaan — atribut asal, BUKAN pagar; **resep global DI DALAM TENANT mengikuti pelanggan, ADR-012/OD-2; antar-tenant terpagar `prescriptions.tenant_id`**), `examined_at DATE`, `examiner_id FK→users NULL`, per mata (prefix `od_`, `os_`): `sph DECIMAL(5,2)`, `cyl DECIMAL(5,2)`, `axis SMALLINT (0–180)`, `add DECIMAL(5,2) NULL`, **`pdr DECIMAL(4,1) NULL`, `pdl DECIMAL(4,1) NULL`** (tempat satu-satunya PD per mata), `pd_total DECIMAL(4,1) NULL`, `prism VARCHAR(30) NULL`, `notes TEXT NULL`, `used_at TIMESTAMP NULL` (terisi saat pertama dipakai transaksi → resep terkunci dari edit). Indeks `(customer_id, examined_at)`.
- **Precal adalah data manual per item penjualan** (ADR-019) — 11 kunci `pvr, pvl, a, b, dbl, ed, vd, pa, wrap, de, type_frame` diinput kasir pada item Lensa RX, tervalidasi `RxPrecalValidator`, tersimpan sebagai JSON `rx_parameters` pada item penjualan (dan tersalin ke item PR saat stok kurang); precal terikat FRAME, bukan turunan resep. **PDR/PDL dilarang memuat** dalam struktur data, tampilan, maupun cetakan precal apa pun — keduanya hanya hidup di resep.

## B.3 Sales & Lab
- **sales**: `number VARCHAR(25) UNIQUE` (E.4), `customer_id FK`, `prescription_id FK NULL` (snapshot resep disalin ke `prescription_snapshot JSON` saat simpan — perubahan resep sesudahnya tidak mengubah transaksi), `branch_id FK`, `cashier_id FK→users`, `doctor_id FK NULL`, `insurance_id FK NULL`, `sale_date DATE`, `subtotal_amount`, `discount_amount default 0`, `tax_amount default 0`, `tax_mode ENUM('inclusive','exclusive') default 'inclusive'`, `total_amount`, `paid_amount default 0` (denormalisasi terpelihara oleh SalesService dalam transaksi yang sama), `status ENUM('active','void') default 'active'`, `void_reason VARCHAR(255) NULL`, `voided_by FK NULL`, `voided_at NULL`, `notes NULL`. Indeks `(branch_id, sale_date)`, `(customer_id)`.
- **sale_items**: `sale_id FK`, `product_id FK NULL` (snapshot nama & harga disimpan), `product_name_snapshot VARCHAR(150)`, `type` (snapshot), `qty INT`, `unit_price DECIMAL(15,2)`, `discount_amount default 0`, `line_total`. Untuk item `rx`: `prescription_ref ENUM('od','os','pair')`.
- **sale_payments**: `sale_id FK`, `method ENUM('cash','debit','qris','transfer','card','points')`, `amount`, `paid_at TIMESTAMP`, `reference VARCHAR(80) NULL`, `received_by FK`. Pembayaran bersifat append-only; koreksi = baris pembalik bertanda negatif dengan `reversal_of_id FK NULL`.
- **sale_installments** (F3): `sale_id FK`, `sequence SMALLINT`, `due_date DATE`, `amount`, `paid_amount default 0`, `status ENUM('open','partial','paid','overdue')` (overdue terhitung).
- **sale_returns / sale_return_items**: `sale_id FK`, `reason`, `refund_method ENUM('cash','transfer','store_credit')`, `total_amount`; item: `sale_item_id FK`, `qty`, `condition ENUM('resale','damaged')`, `amount`.
- **lens_processes**: `sale_id FK UNIQUE`, `status ENUM('waiting','edging','qc','ready','done') default 'waiting'`, `technician_id FK NULL`, `estimated_days SMALLINT NULL`, `started_at TIMESTAMP`, `completed_at TIMESTAMP NULL`. Durasi = accessor: `completed_at ?? now()` − tanggal transaksi, hari kalender penuh; status `done` hanya lewat `complete()` (Bagian D.6).
- **qc_point_templates**: `name VARCHAR(100)`, `is_required BOOL default 1`, `sort_order INT`, `is_active` — bawaan 13 titik (ditanam seeder, dapat dikelola Admin).
- **qc_checks**: `lens_process_id FK`, `qc_point_template_id FK`, `attempt SMALLINT default 1`, `result ENUM('pass','fail')`, `notes VARCHAR(255) NULL` (WAJIB bila fail), `checked_by FK`, `checked_at TIMESTAMP`. Riwayat percobaan tidak ditimpa.
- **lab_orders**: DICABUT (koreksi owner 2026-10-18) — order lab bukan dokumen; pekerjaan lab eksternal berjalan lewat rantai PR -> PO. `purchase_requests` memiliki `sale_id FK NULL` (tautan PR otomatis dari penjualan).

## B.4 Purchasing
- **purchase_requests**: `number UNIQUE`, `requester_id FK`, `branch_id FK` (cabang peminta), `status ENUM('draft','submitted','approved','converted','cancelled') default 'draft'`, `notes NULL`. **purchase_request_items**: `purchase_request_id FK`, `product_id FK`, `qty INT`, `estimated_price DECIMAL(15,2) NULL`, `supplier_id FK NULL` (usulan), `purchase_order_id FK NULL` (terisi saat terkonversi — satu baris PR hanya masuk satu PO).
- **purchase_orders**: `number UNIQUE`, `supplier_id FK`, `branch_id FK` (cabang tujuan), `order_date DATE`, `total_amount` (dihitung dari item), `status ENUM('draft','sent','partial','complete','void') default 'draft'`, `void_reason NULL`, `created_by FK`. **purchase_order_items**: `purchase_order_id FK`, `product_id FK`, `qty_ordered INT`, `qty_received INT default 0` (denormalisasi terpelihara ReceiptService), `unit_cost DECIMAL(15,2)`, `line_total`.
- **receipts**: `number UNIQUE`, `purchase_order_id FK`, `received_date DATE`, `delivery_note_no VARCHAR(80) NULL` (nomor surat jalan supplier), `received_by FK`. **receipt_items**: `receipt_id FK`, `purchase_order_item_id FK`, `qty INT`, `unit_cost_actual DECIMAL(15,2)` (boleh beda dari PO; selisih tercatat apa adanya), baris unik per item PO per receipt.
- **purchase_payments**: `number UNIQUE`, `supplier_id FK`, `method ENUM('transfer','cash','giro','other')`, `amount`, `payment_date DATE`, `reference VARCHAR(80) NULL`, `paid_by FK`, `reversal_of_id FK NULL`. **purchase_payment_allocations**: `purchase_payment_id FK`, `receipt_id FK`, `amount` — alokasi selalu ke **receipt** (bukan PO); jumlah alokasi per pembayaran WAJIB = `purchase_payments.amount` (ditegakkan service + uji rekonsiliasi). Sisa tagihan receipt = total receipt − Σ alokasi (terhitung, tidak disimpan agar tidak menyimpang).
- **purchase_returns / purchase_return_items**: `receipt_id FK`, `reason`, `total_amount`, status kredit (`credit_status ENUM('reduces_payable','supplier_credit')` — mengurangi tagihan bila receipt belum lunas, menjadi kredit supplier bila sudah); item: `receipt_item_id FK`, `qty`, `amount`.

## B.5 Inventory
- **stock_movements**: `product_id FK`, `branch_id FK`, `reason ENUM('initial_stock','sale','sale_void','receipt','purchase_return','transfer_out','transfer_in','sale_return','opname','correction','warranty_return','warranty_out','warranty_in')`, `qty INT` (bertanda: masuk +, keluar −), `reference_type VARCHAR(60)`, `reference_id BIGINT` (dokumen sumber), `balance_after INT` (saldo cabang-produk sesudah gerakan ini), `created_by FK`, `created_at`. Indeks `(product_id, branch_id, created_at)`, `(reference_type, reference_id)`. Baris tidak pernah di-UPDATE/DELETE (ditegakkan policy + tidak ada rute/service untuk itu). Saldo stok resmi = nilai `balance_after` gerakan terakhir per (produk, cabang) — satu-satunya sumber kebenaran stok; tabel ringkasan cache diperbolehkan hanya bila dapat dibangun ulang penuh dari movements dan lulus uji kesetaraan.
- **stock_transfers**: `number UNIQUE`, `from_branch_id FK`, `to_branch_id FK`, `source_type ENUM('purchase_order','branch_stock')`, `source_id BIGINT NULL` (PO asal bila dari PO), `status ENUM('sent','received','cancelled') default 'sent'`, `sent_by FK`, `received_by FK NULL`, `received_at NULL`. **stock_transfer_items**: `stock_transfer_id FK`, `product_id FK`, `qty INT`. Stok berkurang di asal saat `sent` (`transfer_out`), bertambah di tujuan saat `received` (`transfer_in`); selisih waktu = stok dalam perjalanan yang tetap terlihat di laporan.
- **stock_opnames**: `branch_id FK`, `scheduled_date DATE`, `status ENUM('scheduled','counting','review','approved','cancelled')`, `approved_by FK NULL`. **stock_opname_items**: `stock_opname_id FK`, `product_id FK`, `system_qty INT` (snapshot saat hitung dimulai), `counted_qty INT NULL`, `difference INT` (terhitung), `movement_id FK NULL` (gerakan `opname` yang terbit saat approved).

## B.6 Accounting
- **accounts**: `code VARCHAR(20) UNIQUE` (mengikuti pemetaan kode selaras Coretax DJP; bagan bawaan ditanam seeder), `name VARCHAR(120)`, `group ENUM('asset','liability','equity','revenue','expense')`, `parent_id FK NULL`, `branch_id FK NULL` (NULL = akun gabungan; baris jurnal membawa dimensi cabang), `is_active`. Akun terpakai jurnal tidak dapat dihapus (hanya nonaktif).
- **journals**: `number UNIQUE`, `journal_date DATE`, `source_type VARCHAR(60)` (mis. `sale`, `purchase_payment`, `manual`, `reversal`), `source_id BIGINT NULL`, `description VARCHAR(255)`, `period_locked BOOL default 0` (terhitung dari period_locks), `reversal_of_id FK NULL`, `created_by FK NULL` (NULL = posting otomatis sistem). **journal_lines**: `journal_id FK`, `account_id FK`, `branch_id FK NULL` (dimensi cabang untuk LR per cabang), `debit DECIMAL(15,2) default 0`, `credit DECIMAL(15,2) default 0`, `memo NULL`. Kekangan: Σdebit = Σkredit per jurnal — ditegakkan service posting + uji; tidak ada baris dengan debit dan kredit sama-sama > 0.
- **fixed_assets**: `name`, `acquired_at DATE`, `acquisition_cost`, `useful_life_months INT`, `method ENUM('straight_line') default 'straight_line'`, `accumulated_amount default 0`, `status ENUM('active','disposed')`. Penyusutan berjalan sebagai jurnal berkala oleh job terjadwal.
- **period_locks**: `period CHAR(7)` (`YYYY-MM`) UNIQUE, `locked_by FK`, `locked_at`, `unlocked_by FK NULL`, `unlock_reason NULL`. Periode terkunci menolak semua posting bertanggal di dalamnya di semua modul (diperiksa AccountingService sebelum posting apa pun).
- **payrolls** (F1/F4): `employee_user_id FK`, `period CHAR(7)`, `base_amount`, `allowance_amount default 0`, `deduction_amount default 0`, `net_amount` (terhitung), `journal_id FK NULL`.
- **commissions**: `type ENUM('doctor','technician','sales')`, `beneficiary_doctor_id FK NULL`, `beneficiary_user_id FK NULL`, `source_sale_id FK`, `base_amount`, `rate DECIMAL(5,2)`, `amount` (terhitung), `period CHAR(7)`, `status ENUM('accrued','corrected','paid')` — koreksi dari transaksi void menjadi baris penyeimbang, bukan edit.

## B.7 Membership, Comms, Platform
- **membership_levels**: `name`, `min_accumulation DECIMAL(15,2)`, `point_multiplier DECIMAL(4,2) default 1`, `benefits TEXT NULL`, `is_active`. **points_ledger**: `customer_id FK`, `type ENUM('earn','redeem','expire','reversal')`, `points INT` (bertanda), `reference_type/id` (transaksi sumber), `expires_at DATE NULL`, `created_at` — saldo = Σ baris; penukaran poin adalah metode bayar `points` di `sale_payments` dengan nilai tukar dari settings.
- **wa_gateways**: `provider VARCHAR(50)`, `sender_number VARCHAR(30)`, `credentials_encrypted TEXT` (terenkripsi Laravel `encrypted` cast; tidak pernah ditampilkan penuh di UI/log), `is_active`. **Penyedia bawaan DIPUTUSKAN owner 2026-10-18: Meta Cloud API LANGSUNG** (keputusan "untuk sementara" — dapat diganti penyedia lain lewat antarmuka ADR-009 tanpa mengubah pemanggil). Seluruh kredensial & konfigurasi gateway (phone_number_id, WABA ID, access token, verify token, versi API, nomor pengirim, aktif/nonaktif) diatur dari **UI Pengaturan** (layar Gateway WA di PRD-Detail Komunikasi: kredensial tersimpan aman & tersamar, status koneksi, tombol uji kirim) — BUKAN dari .env. **wa_templates**: `key VARCHAR(60) UNIQUE` (`invoice, pickup_reminder, after_service, birthday, campaign`), `body TEXT` (variabel `{nama}`, `{nomor}`, `{total}`, `{cabang}`), `approved_by FK NULL` (template campaign WAJIB). **wa_messages**: `customer_id FK NULL`, `phone VARCHAR(30)`, `template_key NULL`, `body TEXT`, `direction ENUM('out','in')`, `status ENUM('queued','sent','failed','delivered')`, `trigger VARCHAR(60)` (`manual|invoice|reminder|campaign|birthday`), `sent_by FK NULL`, `created_at`. **wa_campaigns**: `name`, `segment JSON` (kriteria penerima), `template_id FK`, `recipient_count INT`, `approved_by FK`, `status ENUM('draft','approved','sending','done','cancelled')`.
- **audit_logs**: `user_id FK NULL`, `action VARCHAR(80)`, `subject_type VARCHAR(60)`, `subject_id BIGINT NULL`, `branch_id FK NULL`, `old_values JSON NULL`, `new_values JSON NULL`, `ip VARCHAR(45) NULL`, `created_at` — hanya INSERT dari aplikasi; dibaca peran tertinggi.
- **api_tokens**: `name`, `token_hash CHAR(64) UNIQUE` (hash saja yang disimpan), `scopes JSON` (selalu read-only), `last_used_at NULL`, `revoked_at NULL`.
- **backups**: `filename`, `size_bytes BIGINT`, `destination ENUM('local','drive')`, `verified BOOL default 0`, `created_at` — "berhasil" hanya bila `verified = 1` sesudah uji buka.

# BAGIAN C — REGISTRY IZIN & MATRIKS PERAN (kontrak)

## C.1 Registry izin lengkap (string kanonis; di luar daftar ini = bug)
- **dashboard**: `dashboard.view`
- **sales**: `sales.view, sales.create, sales.update, sales.void, sales.pay, sales.discount.high` (diskon di atas ambang settings), `sales.return`
- **customers**: `customers.view, customers.create, customers.update, customers.deactivate`
- **clinical**: `prescriptions.view, prescriptions.create, prescriptions.print, medical_notes.view` (catatan rekam medis)
- **catalog**: `products.view, products.create, products.update, products.deactivate`
- **lab**: `lab.view, lab.assign, lab.qc, lab.complete, lab.order.manage`
- **purchasing**: `pr.view, pr.create, pr.approve, po.view, po.create, po.update, po.void, receipts.create, payments.create, payments.collective, purchases.return`
- **inventory**: `stock.view, stock.transfer, stock.receive_transfer, stock.opname, stock.import_initial` (dipagari policy eksplisit di luar string ini), `stock.correct`
- **accounting**: `accounts.view, accounts.manage, journals.view, journals.manual, journals.reverse, reports.financial, periods.lock, periods.unlock, assets.manage, payroll.manage`
- **membership**: `members.view, members.manage, points.adjust` (penyesuaian manual ber-alasan)
- **reports**: `reports.sales, reports.purchases, reports.stock, reports.analytics, reports.export`
- **comms**: `wa.send, wa.inbox, wa.templates, wa.campaigns, wa.campaigns.approve`
- **platform**: `users.manage, roles.manage, branches.manage, settings.manage, audit.view, backups.manage, api_tokens.manage`, dan khusus aktor platform SaaS: `plans.manage, tenants.manage` (gerbang panel Platform bersama middleware `platform` — keduanya wajib, satu saja tidak cukup)
- **master**: `suppliers.manage, insurances.manage, doctors.manage`

## C.2 Matriks peran bawaan (ditanam seeder; dapat diubah Owner lewat UI Peran)
| Kelompok | Owner | Admin | Kasir | Gudang | Cabang | Refraksionis | Teknisi |
|---|---|---|---|---|---|---|---|
| Dashboard | ✔ semua cabang | ✔ | ✔ cabang | ✔ cabang | ✔ cabang | ✔ cabang | antrean saja |
| Penjualan buat/bayar | ✔ | ✔ | ✔ | ✘ | ✔ | ✘ | ✘ |
| Void penjualan | ✔ | izin | ✘ | ✘ | ✘ | ✘ | ✘ |
| Resep buat/cetak | ✔ | ✔ | lihat | ✘ | lihat | ✔ | ✘ |
| Katalog kelola | ✔ | ✔ | lihat | lihat | lihat | lihat | ✘ |
| Lab & QC | ✔ | ✔ | lihat status | ✘ | lihat status | ✘ | ✔ (qc, assign diri) |
| PR buat / approve | ✔ | ✔ | ✘ | buat | buat | ✘ | ✘ |
| PO & penerimaan | ✔ | ✔ | ✘ | ✔ | terima di cabang | ✘ | ✘ |
| Bayar tunggal/kolektif | ✔ | izin | ✘ | ✘ | ✘ | ✘ | ✘ |
| Transfer & opname | ✔ | ✔ | ✘ | ✔ | terima | ✘ | ✘ |
| Impor stok awal | policy khusus | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |
| Akuntansi & keuangan | ✔ | lihat | ✘ | ✘ | ✘ | ✘ | ✘ |
| Laporan & ekspor | ✔ | izin per laporan | ✘ | stok saja | cabang saja | ✘ | ✘ |
| WA kirim / kampanye | ✔/setujui | kirim | kirim dari transaksi | ✘ | kirim | ✘ | ✘ |
| Pengguna/peran/pengaturan | ✔ | ✘ | ✘ | ✘ | ✘ | ✘ | ✘ |
"izin" = tidak bawaan, hanya bila Owner memberi string-nya secara eksplisit. Aktor `is_platform` tidak menerima satu pun izin bisnis dari matriks ini.

# BAGIAN D — SPESIFIKASI PERILAKU PER MODUL

Format per modul: **Service & method publik** · **Event** · **Validasi & kasus tepi yang WAJIB ditangani**.

## D.1 Identity
- Service: `UserService.createUser/updateUser/deactivateUser`, `RoleService.syncPermissions`, `BranchContext.for(user)` → `managedBranchIds(): int[]`, `hasGlobalAccess(): bool`.
- Kasus tepi: pengguna tidak dapat menonaktifkan dirinya; tidak dapat mengubah peran/izin dirinya sendiri; perubahan izin tercatat di `audit_logs` dan berlaku pada request berikutnya.

## D.2 Master
- Service: CRUD aktif/nonaktif untuk branches/suppliers/insurances/doctors; `SettingsService.get/set` dengan daftar kunci yang diizinkan (allowlist).
- Kasus tepi: cabang yang memiliki transaksi tidak dapat dinonaktifkan bila masih ada proses menggantung (orderan belum done, transfer belum diterima) — sistem menolak dengan daftar penghalangnya.

## D.3 Catalog
- Service: `ProductService.create/update/deactivate`; pembacaan stok selalu lewat Inventory (`currentStock(productId, branchId)`).
- Kasus tepi: SKU duplikat ditolak; produk terpakai transaksi tidak dapat diubah tipenya; harga berubah tidak memengaruhi snapshot transaksi lama.

## D.4 CRM
- Service: `CustomerService.create/update/deactivate`; penggabungan duplikat (merge) adalah operasi Owner dengan pemetaan ulang referensi + audit (bukan hapus).
- Kasus tepi: nomor WA duplikat memberi peringatan (bukan blokir) karena keluarga dapat berbagi nomor.

## D.5 Clinical
- Service: `PrescriptionService.create` (mengunci begitu `used_at` terisi) — **precal BUKAN turunan resep** (ADR-019): precal manual 11 kunci hidup di item penjualan; payload `rx_parameters` dilarang memuat kunci PDR/PDL/PD (diuji otomatis: pemindaian kunci pada payload tersimpan).
- Kasus tepi: AXIS wajib 0–180 bila CYL ≠ 0; resep untuk transaksi harus milik pelanggan transaksi itu.

## D.6 Sales & Lab (satu alur, dua modul)
- `SalesService.createSale(items, payments)` — satu transaksi DB: validasi total (Σ baris − diskon + pajak = total; Σ pembayaran awal ≤ total), stok keluar via Inventory (frame/aksesori tidak boleh negatif; lensa boleh negatif dengan bendera backorder), snapshot resep, jurnal via event `SaleCompleted`.
- `SalesService.addPayment` (append-only; lunas terhitung dari Σ pembayaran vs total), `voidSale(reason)` — membalik stok, pembayaran (baris pembalik), jurnal (jurnal balik), komisi (baris penyeimbang), poin.
- `LensProcessService.assignTechnician`, `complete()` — syarat: proses ada, belum done, (F2) semua QC wajib pass; menulis `completed_at` sekali; pemanggilan kedua **ditolak** `ValidationException`. `ProcurementStatusService.stageFor` menghitung status proses lensa TURUNAN dari rantai PR->PO->penerimaan->transfer (stock/pr/po/ordered/received/shipped/done); transisi ke `edging` ditolak selama lensa penjualan belum diterima (`lensesAvailable`). `PurchaseRequestService.createAutomatic` membuat PR submitted tertaut `sale_id` dari transaksi penjualan untuk kekurangan lensa (RX selalu; stok/softlens hanya kekurangannya).
- Kasus tepi: void pada penjualan yang proses lensanya sudah done ditolak — harus lewat retur; diskon di atas ambang settings membutuhkan `sales.discount.high`.

## D.7 Purchasing
- `PurchaseRequestService.submit/approve/convertToPo` (konversi menandai item PR; penggabungan multi-PR hanya se-supplier, satu transaksi DB).
- `PurchaseOrderService.create/fromRequests/void`; `ReceiptService.store` — menambah `qty_received`, stok masuk cabang tujuan PO, jurnal persediaan via event `ReceiptStored`; penerimaan melebihi sisa pesanan ditolak kecuali pemanggil memegang izin approve dan memberi alasan (tercatat).
- `PurchasePaymentService.paySingle(receipt, amount)`; **`CollectivePaymentService.process(supplier, amount, method, date)`** — kontrak keras: (1) kumpulkan receipt belum lunas supplier itu, urut `received_date` ASC (FIFO); (2) **validasi seluruhnya sebelum `DB::transaction` dibuka** (amount > 0, ≤ total sisa, supplier valid); (3) di dalam satu transaksi: buat pembayaran + alokasi per receipt sampai amount habis; (4) semua pengecualian ditangkap sebagai `\Throwable` di controller dan menjadi pesan ramah — tidak pernah 500 mentah; (5) tidak ada keadaan tercatat-sebagian dalam kegagalan apa pun (diuji dengan injeksi kegagalan di tengah alokasi).
- `PurchaseReturnService.store` — stok keluar, nilai mengurangi sisa tagihan receipt atau menjadi kredit supplier sesuai `credit_status`.
- Kasus tepi: membayar receipt yang sudah lunas ditolak; pembayaran ke supplier nonaktif ditolak; tanggal pembayaran di periode terkunci ditolak AccountingService.

## D.8 Inventory
- `InventoryService.recordMovement(product, branch, reason, qty, reference)` — satu-satunya penulis; memeriksa saldo frame/aksesori tidak negatif; menulis `balance_after` dalam transaksi yang sama dengan dokumen sumbernya (dipanggil di dalam transaksi service pemanggil).
- `TransferService.send(poOrBranchStock, items, toBranch)` (menghitung sisa yang dapat dikirim dari PO: diterima − sudah terkirim), `receive(transfer)` — idempoten: menerima dua kali ditolak.
- `InitialStockImportService.preview(file)` (validasi penuh: SKU ada, cabang ID/Nama valid, qty ≥ 0; menghasilkan daftar kesalahan per baris) → `commit(previewToken)` (satu transaksi; pergerakan `initial_stock` + jurnal persediaan); dipagari **policy eksplisit** `InitialStockImportPolicy` (peran tertinggi & bukan aktor platform), bukan string izin; komitmen kedua untuk cabang yang sama ditolak tanpa proses koreksi resmi.
- `OpnameService` — snapshot `system_qty` saat counting dimulai; approval menerbitkan gerakan selisih; selama counting, mutasi cabang itu dikunci kecuali Owner.
- Kasus tepi: transfer ke cabang yang sama ditolak; stok dalam perjalanan terlihat terpisah di laporan (tidak dihitung sebagai stok tujuan).

## D.9 Accounting
- `PostingService.post(sourceType, sourceId, date, lines[])` — memeriksa periode tidak terkunci, Σdebit = Σkredit, akun aktif; dipanggil listener event saja (atau `journals.manual` berizin). `reverse(journal, reason)` membuat jurnal balik зеркальный dan menandai hubungan.
- Listener bawaan (template Bagian E.1): `SaleCompleted, SaleVoided, SaleReturned, ReceiptStored, PurchaseReturned, PurchasePaymentPosted (tunggal & kolektif), TransferReceived (tidak menjurnal — perpindahan internal), OpnameApproved, PointsRedeemed, PayrollPosted, DepreciationRun`.
- `PeriodService.lock/unlock` (unlock hanya Owner + alasan, teraudit). `FinancialReportService.balanceSheet(period, branch?)`, `incomeStatement(period, branch?)` (memakai dimensi `branch_id` di `journal_lines`), `cashFlow(period)`.
- Kasus tepi: posting ke tanggal periode terkunci dari modul mana pun gagal dengan pesan yang menyebut periodenya; jurnal manual tidak dapat memakai akun ringkasan induk.

## D.10 Membership & Comms (F3)
- `PointsService.earn(sale)`, `redeem(sale, points)` (menjadi baris `sale_payments` metode `points` + jurnal liabilitas poin), `expire(run)` terjadwal; penyesuaian manual wajib alasan + `points.adjust`.
- `MemberPortalService.issueToken(customer)` (token acak, hanya hash disimpan, dapat dicabut) — portal menampilkan poin, riwayat beli, status orderan milik token itu saja.
- `MessagingService.send(templateKey, customer, context)` — antrean + coba lagi (3×, jeda menaik); `CampaignService` menolak kirim tanpa `approved_by`; opt-out pelanggan (kolom bendera di customers) selalu dihormati; kegagalan gateway tidak menggagalkan transaksi pemicu (pengiriman bersifat async dari event).

## D.11 Reporting & Platform
- `ReportService` — setiap laporan menerima (periode, cabang?, filter) dan selalu menerapkan pagar cabang aktor; ekspor memakai filter yang sama persis dengan tampilan (diuji kesetaraan total layar vs berkas).
- `BackupService.run(destination)` — dump DB + arsip dokumen, verifikasi buka, catat `backups`; kegagalan menaikkan bendera Dashboard Owner.
- `AuditLogger` — dipanggil service pada mutasi sensitif dengan old/new values yang disaring (tanpa kredensial/kolom rahasia).

# BAGIAN E — MESIN LINTAS MODUL (kontrak)

## E.1 Peta Posting Akuntansi (template jurnal; akun diselesaikan dari CoA bawaan)
| Peristiwa | Debit | Kredit | Keterangan |
|---|---|---|---|
| Penjualan lunas tunai/QRIS/dll. | Kas/Bank (per metode) | Pendapatan Penjualan; Persediaan berkurang terpisah | Pajak keluaran bila mode exclusive |
| Penjualan belum lunas | Piutang Pelanggan (sisa) + Kas (yang dibayar) | Pendapatan Penjualan | HPP: Debit HPP / Kredit Persediaan (nilai modal item) |
| Cicilan diterima | Kas/Bank | Piutang Pelanggan | |
| Retur penjualan (resale) | Pendapatan (kontra)/Retur Penjualan | Kas/Kredit Toko; Persediaan kembali: Debit Persediaan / Kredit HPP | Barang damaged tidak kembali ke persediaan jual |
| Penerimaan barang (receipt) | Persediaan | Utang Supplier | Pada harga aktual receipt |
| Retur pembelian | Utang Supplier (atau Piutang Kredit Supplier) | Persediaan | Sesuai `credit_status` |
| Pembayaran supplier (tunggal/kolektif) | Utang Supplier | Kas/Bank | Satu jurnal per dokumen pembayaran, alokasi tidak mengubah jurnal |
| Impor stok awal | Persediaan | Ekuitas/M modal Persediaan Awal | Per komitmen impor |
| Opname selisih kurang | Beban Selisih Opname | Persediaan | Selisih lebih sebaliknya |
| Poin diterbitkan | Beban Program Loyalitas | Liabilitas Poin | Saat earn |
| Poin ditukar | Liabilitas Poin | Pendapatan Penjualan | Porsi pembayaran poin |
| Komisi terhutang | Beban Komisi | Utang Komisi | Per periode; dibayar: sebaliknya ke Kas |
| Gaji (payroll) | Beban Gaji | Kas/Bank (+ Utang PPh bila berlaku) | Per periode payroll |
| Penyusutan bulanan | Beban Penyusutan | Akumulasi Penyusutan | Job terjadwal per aset aktif |
Semua template membawa `branch_id` dokumen sumber ke `journal_lines` (untuk LR per cabang); akun Kas/Bank dipilih dari metode bayar lewat pemetaan settings.

## E.2 Matriks Pergerakan Stok
| Peristiwa | reason | Arah | Referensi |
|---|---|---|---|
| Impor awal | `initial_stock` | + | dokumen impor |
| Penjualan | `sale` | − | sale |
| Void penjualan | `sale_void` | + | sale |
| Retur penjualan resale | `sale_return` | + | sale_return |
| Penerimaan PO | `receipt` | + | receipt |
| Retur pembelian | `purchase_return` | − | purchase_return |
| Transfer kirim | `transfer_out` | − (asal) | stock_transfer |
| Transfer terima | `transfer_in` | + (tujuan) | stock_transfer |
| Opname | `opname` | ± selisih | stock_opname |
| Koreksi berizin | `correction` | ± | dokumen koreksi (wajib alasan) |
| Garansi: barang lama baik kembali | `warranty_return` | + (gudang) | broken_stock (hasil inspeksi baik) |
| Garansi: pengganti keluar | `warranty_out` | − (gudang) | warranty_case |
| Garansi: pengganti masuk | `warranty_in` | + (cabang asal) | warranty_case |

**Modul Garansi (ditambahkan 2026-10-18, penutupan celah paritas Artesis):** kasus per item penjualan (satu kasus aktif per item) mengalir open → waiting_warehouse → old_item_received → replacement_preparing → replacement_shipped → replacement_received → completed; barang lama diinspeksi di cabang bertanda `branches.is_main_warehouse` (baik → `warranty_return` pada biaya penjualan asal; rusak → catatan broken_stocks menunggu write-off ber-alasan, tidak pernah masuk stok jual); pengganti dari stok gudang atau PO khusus; pengiriman pengganti menjurnal Dr Beban Garansi (5-1700)/Cr Persediaan sebesar biaya FIFO; biaya pelanggan dibayar bertahap menjurnal Dr tender/Cr Pendapatan Garansi (4-1300); penyelesaian kasus mensyaratkan pengganti diterima dan biaya lunas.

## E.3 Mesin Status (transisi yang diizinkan; lainnya ditolak)
- **PR**: draft → submitted → approved → converted; submitted → cancelled; draft → cancelled.
- **PO**: draft → sent → partial → complete; draft/sent → void (ber-alasan; void hanya bila belum ada penerimaan — bila sudah ada, selesaikan lewat retur).
- **Receipt**: tidak berstatus (dokumen final saat disimpan; koreksi hanya lewat retur pembelian).
- **Pembayaran (semua jenis)**: final saat disimpan; koreksi hanya baris/jurnal pembalik.
- **Sale**: active → void (syarat D.6). **LensProcess**: waiting → edging → qc → ready → done (boleh menunggu→done hanya bila tidak ada titik QC wajib aktif; lompatan lain ditolak). **PR otomatis**: lahir `submitted` dari penjualan; dibatalkan otomatis bila penjualan di-void selagi masih submitted.
- **Transfer**: sent → received; sent → cancelled (mengembalikan stok asal lewat gerakan penyeimbang otomatis).
- **Opname**: scheduled → counting → review → approved; counting → cancelled (Owner).
- **Periode**: open → locked → (unlock Owner ber-alasan) → open.
- **Campaign**: draft → approved → sending → done; approved → cancelled.

## E.4 Penomoran Dokumen (otomatis, unik, tidak dipakai ulang)
Format `PREFIX-YYYYMM-NNNN` (urut per bulan per tipe, dihitung dalam transaksi dengan kunci baris penghitung): Sale `INV`, PR `PR`, PO `PO`, Receipt `RCV`, Pembayaran Supplier `PAY`, Pembayaran Kolektif tetap satu `PAY` per dokumen kolektif, Retur Jual `SR`, Retur Beli `BR`, Transfer `TRF`, Jurnal `JU`. Nomor void tetap tercatat dan tidak didaur ulang.

## E.5 Spesifikasi Dokumen Cetak
| Dokumen | Ukuran | Isi wajib |
|---|---|---|
| Struk penjualan | 58mm & 80mm | identitas toko, nomor, item, total, pembayaran, sisa, status proses |
| Orderan kerja (tiket lab) | A5/80mm | nomor, pelanggan, resep ringkas OD/OS, item lensa/frame, estimasi, cabang |
| Resep | A5 | identitas, pasien, grid OD/OS lengkap (termasuk PDR/PDL — hanya di sini), pemeriksa, tanggal |
| Invoice | A4/A5 | rincian penuh + pajak + pembayaran |
| PO ke supplier | A4 | item, qty, harga, syarat, identitas cabang tujuan |
| Label harga/barcode | label 38×25mm (bawaan) | nama, SKU barcode EAN-13/Code 128, harga |
Setiap konteks dokumen memiliki **tombol dokumennya sendiri-sendiri di dalam kelompok tombol sejajar** layar detailnya (ADR-018) yang memuat seluruh dokumen konteks itu (dokumen belum ada → dibuat lalu dibuka di tab baru; sudah ada → dibuka).

# BAGIAN F — PETA RUTE & KONVENSI UI

## F.1 Peta rute (pola REST per sumber daya; semua di belakang auth)
- *Peta rute NYATA (diselaraskan 2026-10-23 dari `route:list`; jalur memakai Bahasa Indonesia):* `GET /dashboard` (+ `/dashboard/data`) · Penjualan: `penjualan` (terminal POS ADR-019 di `penjualan/buat` + pencarian async JSON sepagar `sales.create`; `penjualan/{sale}` + `/bayar` `/void` `/cicilan` `/retur` `/item/{item}/precal` + cetak `invoice|orderan|resep|struk`), garansi `penjualan/garansi`, stok rusak `penjualan/stok-rusak` · Pelanggan: `pelanggan` (+ resep `pelanggan/{c}/resep`, `resep/{id}` + `/cetak` `/duplikat`) · Katalog dua tingkat: `katalog/{type}` + `produk/{id}` + `produk-induk/{id}` (kategori tetap tanpa UI kelola — ADR-015) · Lab: `lab` + `lab/{process}` (`/tugaskan` `/qc` `/status` `/selesai`) + `lab/template-qc` (order lab sebagai dokumen DICABUT 2026-10-18; pekerjaan lab berjalan lewat antrean proses + rantai PR → PO) · Pembelian: `pembelian/pr` (+setujui/konversi), `pembelian/po` (+kirim/void/cetak, `/{po}/terima` + `/distribusi`), `pembelian/bayar-kolektif`, `pembelian/retur`, `supplier` · Stok: `stok`, `stok/pergerakan`, `transfer`, `stok/opname`, `stok/impor` (pratinjau → komitmen), `stok/label` · Akuntansi: `akun`, `jurnal` (+`/balik` `/cetak` `/ekspor`), `buku-besar`, `laporan-keuangan/{arus-kas|laba-rugi|neraca|...}`, `periode` (`/kunci` `/buka`), `aset-tetap` (+`penyusutan`), `payroll` (+ekspor), `komisi` (+posting/bayar/ekspor), `insentif` (aturan/generate/setujui/bayar/ekspor) · Membership: `member`, penyesuaian poin, portal publik `GET /m/{token}` · Laporan: `laporan/{penjualan|pembelian|stok|analitik|piutang|pemakaian-asuransi}` + ekspor · Komunikasi: `wa/inbox`, `wa/template`, `wa/kampanye`, `wa/log`, `wa/webhook` (POST, terverifikasi tanda tangan Meta) · Pengaturan: `cabang`, `pengguna`, `peran`, `pengaturan/umum`, `pengaturan/gateway-wa`, `pengaturan/asisten-ai`, `log-aktivitas`, `pengaturan/backup`, `pengaturan/token-api`, `pengaturan/perangkat-offline`, `asuransi`, `dokter` · Fitur F5: `asisten` (+`/tanya`), `kasir-offline` · API: `GET /api/v1/...` baca-saja ber-token + `api/v1/offline/{snapshot|sync}`.

## F.2 Konvensi UI bersama (mengikat semua modul)

### F.2.0 Bahasa Desain — Clean, Modern, Elegan (keputusan owner 2026-10-17)
Token desain dikunci di satu tempat (variabel tema Tailwind) dan dipakai semua modul tanpa pengecualian:
- **Warna:** emerald pekat = warna utama (aksi primer, tautan, keadaan aktif); emas = aksen terbatas (maksimal satu elemen penekanan per layar: tombol utama atau penanda aktif); latar dominan netral terang (putih/abu sangat muda); teks abu pekat. Dilarang menambah warna dekoratif di luar token. **Pengecualian resmi (dinyatakan 2026-10-23):** chrome sidebar memakai aksen emas pada logo, label kelompok, dan penanda item aktif sebagai identitas merek yang tetap — pengecualian ini hanya berlaku untuk chrome navigasi, bukan isi layar.
- **Tipografi:** Plus Jakarta Sans; skala 12/14/16/20/24/32; angka uang & tabel memakai `font-variant-numeric: tabular-nums`.
- **Bentuk & ruang:** radius sudut 8–12px; bayangan maksimal satu tingkat sangat tipis; spasi mengikuti skala 4px (4/8/12/16/24/32/48); garis pemisah 1px abu muda.
- **Komponen bersama (satu implementasi, dipakai semua modul):** PageHeader, FilterBar, DataTable (sort di header), FormField (label + error), Badge status, Tombol (primer emas/emerald, sekunder garis, bahaya merah teks), **AksiBar/AksiBaris** (kelompok tombol perintah sejajar yang selalu terlihat — ADR-018), **MenuItem** (tombol perintah: primer emerald/garis/bahaya, ukuran reguler & kecil), **GlobalSearch** (selalu di bagian atas; hasil terpagar cabang & izin, dikelompokkan per jenis), Kartu ringkasan, EmptyState, DialogKonfirmasi.
- **Gerak:** transisi 120–200ms pada hover/fokus saja; tanpa animasi dekoratif.
- **Detail = halaman penuh (kontrak):** setiap sumber daya memiliki rute detail sendiri yang me-render halaman penuh; **dilarang** pola preview untuk detail — tanpa modal detail, drawer/slide-over, quick-view, atau popover berisi detail. Klik pada baris/nomor di daftar selalu `GET` ke rute detail. Dialog hanya untuk konfirmasi aksi (dengan alasan bila wajib), bukan untuk menampilkan isi.
1. Daftar: header halaman bersama (judul + tombol aksi utama kanan), bar filter (pencarian, cabang bila global, rentang tanggal, status), tabel dengan **header kolom dapat diklik untuk mengurutkan** (bawaan: terbaru di atas untuk daftar bertanggal), paginasi 25/baris bawaan, keadaan kosong yang menjelaskan + tombol buat.
2. Detail: kepala ringkas, tab/bagian per sub-entitas, tombol dokumen per konteks dan seluruh perintah sebagai **tombol sejajar dalam satu kelompok** (ADR-018; aksi berbahaya void/nonaktif di urutan terakhir kelompok, warna merah, konfirmasi + alasan wajib).
3. Formulir: kesalahan validasi tampil di samping field ( Bahasa Indonesia), tidak pernah halaman error mentah; semua pengiriman yang gagal di server mengembalikan pengguna ke formulir dengan isian utuh.
4. POS: footer ringkasan pembayaran **tetap** (fixed) di bagian bawah layar kasir; antrean lab: penyegaran berkala tanpa muat ulang penuh.
5. Pesan sistem memakai istilah bisnis (bukan istilah teknis): mis. "Periode 2026-09 sudah ditutup" — bukan nama pengecualian.
6. Aksesibilitas dasar: label form eksplisit, kontras teks memenuhi WCAG AA pada teks isi.
7. **Detail halaman penuh, bukan preview:** mengikuti F.2.0 — tidak ada implementasi detail dalam modal/drawer/quick-view di modul mana pun; uji penerimaan UI memeriksa bahwa setiap tautan detail menavigasi ke halaman penuh dan tidak ada komponen preview-detail yang terdaftar di inventaris komponen bersama.
9. **Judul satu kali + pencarian global di atas (keputusan owner 2026-10-17, tata letak direvisi 2026-10-18 — ADR-013):** judul halaman dirender tepat satu kali — dilarang ganda di topbar dan body. Tata letak resmi = **SIDEBAR** (menggantikan Varian A header ramping yang dipilih 2026-10-17; owner menilai sidebar lebih modern dan skalabel sesudah menu melampaui 17 tautan): navigasi adalah sidebar kiri emerald pekat dengan menu terklasifikasi 6 kelompok (Utama; Operasional; Pembelian & Persediaan; Akuntansi; Laporan; Master & Pengaturan) dan submenu buka-tutup; setiap item terpagar izin seperti sebelumnya. Topbar ramping memegang judul halaman (tepat satu kali — body tidak mengulang judul) dan `GlobalSearch` selalu hadir di topbar dengan endpoint pencarian tunggal yang menerapkan pagar cabang + izin sebelum hasil dikembalikan. Di layar sempit sidebar menjadi panel geser (khusus navigasi; detail tetap halaman penuh). Pratinjau yang disetujui owner: `docs/preview/pratinjau-sidebar.html`.
8. **Tombol sejajar — seluruh perintah selalu terlihat (keputusan owner 2026-10-22, ADR-018; menggantikan model dropdown 2026-10-17):** dalam satu layar, seluruh perintah dirender sebagai tombol tersendiri yang selalu terlihat dalam satu kelompok sejajar `AksiBar` (termasuk "Buat Baru" sebagai tombol primer pertama pada layar daftar; dokumen cetak sebagai tombol bergaris berikon printer di kelompok yang sama), dan aksi per baris tabel sebagai deretan tombol kecil `AksiBaris` per baris. Tidak ada perintah yang disembunyikan di dalam dropdown atau menu. Item berbahaya berada di urutan terakhir kelompok dengan gaya bahaya + konfirmasi + alasan lewat DialogKonfirmasi; perintah yang membutuhkan isian dibuka lewat dialog dari tombolnya. Pengecualian tetap hanya navigasi murni (Kembali, paginasi, tautan baris ke detail). Uji penerimaan UI memeriksa bahwa tidak ada komponen dropdown perintah yang terdaftar dan setiap perintah layar hadir sebagai tombol yang terlihat.

# BAGIAN G — PENGUJIAN, CI, DEPLOY, BUKTI

## G.1 Strategi pengujian (per modul, semua WAJIB)
1. **Test perilaku (Feature, HTTP nyata):** alur sukses + penolakan utama per operasi tulis di Bagian D. Test memakai kemampuan aplikasi yang sah (peran/izin dari registry) — dilarang menyisipkan baris izin manual ke DB sebagai jalan pintas.
2. **Matriks keamanan per modul (kasus tetap):** (a) aktor berizin lolos; (b) aktor tanpa izin ditolak 403; (c) aktor cabang lain ditolak pada baca & tulis sumber daya cabang tetangga (IDOR lewat URL langsung); (d) aktor `is_platform` ditolak pada aksi bisnis; (e) pengguna dinonaktifkan ditolak di semua rute; (f) izin dicabut → request berikutnya ditolak.
3. **Test rekonsiliasi uang/stok (modul finansial):** invariant diuji sebagai properti — Σalokasi = pembayaran; Σdebit = Σkredit; saldo stok = gerakan terakhir; total layar laporan = total berkas ekspor.
4. **Test injeksi kegagalan:** pembayaran kolektif digagalkan di tengah alokasi (mock) → nol baris pembayaran/alokasi/jurnal tertinggal; impor stok awal berisi baris rusak → nol baris terimpor pada mode commit tanpa pratinjau bersih.
5. **Test precal manual (ADR-019):** validator 11 kunci (numerik; `de` ∈ {R,L}; `type_frame` maks 100; flag `precal_complete`) + payload `rx_parameters` tersimpan dipindai — tidak ada kunci/nilai PDR/PDL.
6. Target cakupan: service bisnis inti ≥ 90% baris; keseluruhan suite hijau adalah gerbang, bukan rata-rata.
7. **Uji regresi & konkurensi — SELALU (instruksi owner 2026-10-17):** setiap perubahan yang menyentuh hitungan uang/stok — dan setiap gerbang fase tanpa kecuali — WAJIB menjalankan (a) **suite regresi penuh** (seluruh test yang pernah ditulis, bukan hanya test modul yang berubah) dan (b) **suite konkurensi** khusus alur keuangan/stok dengan skenario tetap: dua submit bersamaan pada operasi yang sama tercatat tepat satu kali (idempotensi); dua pembayaran bersamaan pada tagihan yang sama tidak pernah melampaui nilai tagihan dan alokasi FIFO tetap konsisten; dua penjualan bersamaan atas unit stok terakhir tidak pernah menghasilkan stok negatif (satu transaksi menang, sisanya gagal bersih); pembayaran kolektif yang dijalankan dua kali bersamaan tidak menghasilkan alokasi ganda; penyelesaian (Complete) proses yang sama secara bersamaan tercatat tepat satu kali; dua penerimaan bersamaan pada satu PO tidak pernah melampaui qty PO secara kumulatif. Suite konkurensi berjalan terhadap MySQL nyata (bukan SQLite) dengan proses/transaksi paralel dan kunci baris, dan invariant G.1.3 diperiksa sesudah setiap skenario. Kegagalan satu skenario pun = gerbang GAGAL, pekerjaan berhenti dan dilaporkan — tidak ada "lolos bersyarat" untuk hitungan keuangan.

## G.2 Pipeline CI (GitHub Actions, sejak Fase 0)
Job berurutan, semua wajib hijau untuk merge: (1) **lint & format** (Pint); (2) **pindai statis** — pola terlarang: pemanggilan `bc*`, pemakaian float untuk uang di service (daftar pola di repo), string izin di luar registry, query tanpa pagar cabang pada controller daftar (daftar periksa dibantu uji arsitektur PHPUnit `ArchitectureTest`); (3) **Unit**; (4) **Feature + Security matrix**; (5) **build aset** (Tailwind) tanpa meng-commit hasil build. Aturan cabang: PR ke `main`, merge hanya sesudah CI hijau + review; tidak ada push langsung ke `main`.

## G.3 Blueprint Deploy (per instalasi — dua topologi)
- **Topologi A (instalasi per tenant):** setiap tenant = satu instalasi + satu database di server bersama; isolasi tenant ditegakkan batas instalasi. Inilah topologi yang dilayani `docs/DEPLOY-RUNBOOK.md` saat ini.
- **Topologi B (satu instansi multi-tenant):** satu instalasi melayani semua tenant; isolasi WAJIB ditegakkan Bagian J.2. **Belum boleh produksi** sebelum gerbang Fase 1–3 Roadmap lulus (keputusan topologi = OD-1 di PRD).
- Layout server: `/home/<deploy-user>/Artesis-SaaS` (clone pull-only), PHP-FPM 8.3, MySQL 8, nginx vhost HTTPS (sertifikat otomatis), antrean database dijalankan supervisor (`queue:work`), scheduler cron `schedule:run` (penyusutan, kedaluwarsa poin, pengingat, backup).
- Langkah rilis baku: catat HEAD lama sebagai **jangkar rollback** → pull fast-forward → `composer install --no-dev` → `migrate --force` (hanya migrasi yang sudah lulus CI) → `view:clear` → **tanpa** `config:cache/route:cache/optimize` sampai ada langkah verifikasi cache tersendiri yang disetujui owner (aturan berdiri warisan insiden Artesis) → verifikasi pasca-deploy: HEAD sesuai, health check HTTP, grep kontrak kritis (mis. nol pemanggilan bc*, catch `\Throwable` pada controller pembayaran), porcelain baseline.
- **Paritas lingkungan (gerbang sebelum rilis pertama & setiap perubahan server):** `php -v` = 8.3.x; `php -m` memuat semua ekstensi daftar A.1; versi MySQL 8.0; checklist ditandatangani di laporan rilis.
- Rollback: `git reset --hard <jangkar>` + `view:clear`; migrasi yang tidak kompatibel mundur dilarang masuk rilis tanpa rencana rollback data tertulis.

## G.4 Standar Bukti Rilis (mengikat, warisan pengerasan Artesis)
Setiap laporan merge/deploy WAJIB melampirkan output mentah yang dapat ditanya ulang: identitas merge commit & waktu merge dari API GitHub, ID run CI + status + angka test/assertion persis, HEAD server sebelum/sesudah, reflog server, hasil grep verifikasi, dan kode HTTP kesehatan. Narasi tanpa lampiran = tidak lulus audit. Klaim waktu harus koheren dengan urutan peristiwa (gerbang CI selesai sebelum deploy dimulai).

# BAGIAN H — ROADMAP PEMBANGUNAN PRA-SAAS (ARSIP HISTORIS — SELESAI)

> **Status bagian ini:** catatan pembangunan era produk single-business (Fase 0–5, selesai 2026-10-18 + penutupan celah sesudahnya). Disimpan sebagai bukti & riwayat keputusan, **bukan** kendali urutan saat ini. Kendali urutan yang berlaku adalah `docs/ROADMAP.md` (fase SaaS berbasis dependensi).

Notasi: `[F<fase>.<nomor>]` tugas; "→" bergantung pada. Fase tidak dimulai sebelum gerbang keluar fase sebelumnya lulus dan owner memberi kata mulai.

## Fase 0 — Fondasi
- [F0.1] Scaffold Laravel 13 + struktur modul `app/Modules/*` + CI (G.2) hijau pada commit kerangka.
- [F0.2] Skema Identity & Master (B.1) + seeder peran/izin (Bagian C) + UI login, pengguna, peran, cabang.
- [F0.3] `BranchContext` + global scope + pola Policy + `ArchitectureTest` awal → F0.2.
- [F0.4] Audit log + helper `Money` + pemindai pola terlarang di CI → F0.1.
- [F0.5] Mesin dokumen dasar (layout cetak + satu dokumen contoh) + halaman daftar pola bersama (F.2).
**Gerbang keluar F0:** CI hijau penuh; matriks keamanan (G.1.2) lulus pada modul Identity; checklist paritas lingkungan terdokumentasi; owner menyetujui pola UI pada satu modul contoh.

## MVP — Inti Operasional
- [M.1] Catalog (B.2) + UI per tipe → F0.
- [M.2] CRM + Clinical: pelanggan, resep (grid OD/OS), precal (kini MANUAL per item — ADR-019 mencabut PrecalService otomatis; test pemindaian PDR/PDL tetap berlaku pada alur manual) → F0.
- [M.3] Sales inti: transaksi + item + pembayaran append-only + snapshot resep + dokumen orderan/resep/invoice (satu tombol per konteks) → M.1, M.2.
- [M.4] Inventory inti: `stock_movements` + `InventoryService` + stok per cabang → M.1; integrasi keluar-stok di Sales → M.3.
- [M.5] Lab dasar: `lens_processes`, antrean, assign teknisi, `complete()` + durasi membeku → M.3.
- [M.6] Purchasing: PR (submit/approve/convert, gabung se-supplier) → PO → receipt bertahap → pembayaran tunggal → **pembayaran kolektif FIFO** + test injeksi kegagalan → M.1, M.4.
- [M.7] Transfer distribusi dari detail PO + penerimaan cabang tujuan → M.6.
- [M.8] Impor stok awal (preview→commit, policy eksplisit) → M.4.
- [M.9] Jurnal otomatis dasar: listener Sale/Receipt/Payment/Returns + jurnal balik untuk void → M.3, M.6.
- [M.10] Laporan inti (penjualan, pembelian bruto/retur/neto, stok, utang supplier) + ekspor setara layar → M.9.
- [M.11] Dashboard operasional per peran → M.3, M.5, M.6.
**Gerbang keluar MVP:** seluruh test G.1 hijau termasuk injeksi kegagalan kolektif; siklus toko penuh di instalasi uji (jual→proses→Complete; PR→PO→terima→bayar kolektif; transfer→terima) tanpa selisih stok/jurnal; smoke test owner; laporan rilis memenuhi G.4.

## Fase 1 — Akuntansi Penuh
- [F1.1] CoA + seeder bagan akun Coretax + UI akun → MVP.
- [F1.2] Buku besar + penelusuran jurnal → F1.1.
- [F1.3] Laporan keuangan: Neraca, Laba Rugi (total & per cabang), Arus Kas → F1.2.
- [F1.4] Aset tetap + job penyusutan terjurnal → F1.1.
- [F1.5] Tutup buku: period_locks ditegakkan di PostingService + UI lock/unlock → F1.3.
- [F1.6] Dashboard keuangan (piutang/utang/LR ringkas per cabang) → F1.3.
- [F1.7] *(keputusan owner: di sini atau Fase 4)* Payroll + komisi dokter/teknisi dasar → F1.1.
**Gerbang keluar F1:** satu periode contoh rekonsiliasi 100% jurnal↔laporan; posting ke periode terkunci terbukti ditolak dari minimal tiga modul berbeda; laporan rilis G.4.

## Fase 2 — QC Faset & Proses Lab
- [F2.1] Template titik QC (seeder 13 titik) + UI kelola → MVP.
- [F2.2] Perekaman QC per percobaan + syarat Complete menegakkan QC → F2.1.
- [F2.3] **DICABUT & DIGANTI (koreksi owner 2026-10-18)**: order lab sebagai dokumen mandiri + jurnal biaya lab dibuang (di Artesis order lab = rantai PR -> PO; dokumen paralel membuka risiko HPP ganda). Penggantinya paritas Artesis: PR otomatis dari penjualan untuk kekurangan lensa + status proses lensa turunan + edging tergerbang penerimaan lensa.
**Gerbang keluar F2:** test negatif membuktikan Complete mustahil tanpa QC wajib lulus DAN edging mustahil sebelum lensa diterima; satu orderan uji ujung-ke-ujung mengikuti rantai transaksi → PR → PO → terima → edging → QC → Complete.

## Fase 3 — Penjualan Diperluas, WhatsApp, Membership
- [F3.1] DP + split payment + PPN mode per transaksi → MVP.
- [F3.2] Cicilan & piutang pelanggan + daftar umur piutang → F3.1, F1 (jurnal piutang final).
- [F3.3] Dokter perujuk di transaksi + komisi dokter (menghubungi F1.7 bila payroll di F1) → F3.1.
- [F3.4] Gateway WA: antarmuka + penyedia resmi + antrean/coba-lagi + log → penyedia DIPUTUSKAN owner 2026-10-18: Meta Cloud API langsung (sementara); kredensial diatur via UI Pengaturan.
- [F3.5] Pengiriman invoice & reminder otomatis (pengambilan, after-service, ulang tahun) → F3.4.
- [F3.6] Membership: level, poin (earn/redeem/expire + jurnal liabilitas), follow-up → F3.1.
- [F3.7] Portal member ber-token → F3.6, F3.4.
- [F3.8] Inbox dua arah + kampanye berpagar persetujuan → F3.4.
**Gerbang keluar F3:** skenario DP+split+cicilan rekonsiliasi penuh di jurnal & laporan; pesan WA tercatat 100% di log; poin seimbang (liabilitas = Σ ledger poin aktif).

## Fase 4 — Inventaris Lanjut, Analitik, API
- [F4.1] Opname terjadwal ujung-ke-ujung → MVP.
- [F4.2] **Gerbang costing**: audit metode berjalan → laporan pilihan (tetap rata-rata vs FIFO) → keputusan owner tertulis → implementasi metode terpilih + migrasi nilai persediaan. **DIPUTUSKAN owner 2026-10-18: FIFO per cabang (ADR-014).** Audit membuktikan metode berjalan BUKAN FIFO/rata-rata: HPP = qty × cost_price master statis, penerimaan tidak pernah memperbarui cost_price, stock_movements tanpa harga — buku besar Persediaan dan valuasi laporan stok dapat menyimpang permanen. Implementasi FIFO **SELESAI di Fase 4** (gerbang Fase 4 hijau; ADR-014 berlaku penuh).
- [F4.3] Expiry softlens + peringatan & blokir jual kedaluwarsa → F4.1 tidak wajib.
- [F4.4] Label barcode massal → MVP.
- [F4.5] Supplier Analytics + Sales Analytics (kesetaraan angka dengan laporan sumber diuji) → F1.
- [F4.6] API read-only ber-token + pencatatan pemakaian → F0 pola token.
- [F4.7] Payroll (bila owner menundanya dari F1) → F1.
**Gerbang keluar F4:** satu siklus opname terekonsiliasi; keputusan costing terdokumentasi sebagai ADR; analitik = laporan sumber pada periode uji.

## Fase 5 — Riset (setiap butir: spike → laporan kelayakan → keputusan owner terpisah)
- [F5.1] Asisten AI bisnis read-only (owner-only; pagar data di lapisan query; jawaban menyertakan dasar data; kredensial AI milik pemilik).
- [F5.2] Fitting AI (pengukuran wajah; tanpa retensi foto; ranking dari stok cabang).
- [F5.3] Offline kasir (antrean lokal, nomor struk per perangkat, resolusi konflik stok & pembayaran saat sinkronisasi).
**Gerbang keluar F5:** laporan kelayakan per butir (biaya, risiko privasi, kompleksitas) — tidak ada kode produksi dari fase ini tanpa keputusan bangun tersendiri.

# BAGIAN I — KEPUTUSAN TEREKAM (ADR ringkas) & YANG TERBUKA

- **ADR-001** Monolit modular, bukan microservices — kesederhanaan operasional satu bisnis.
- **ADR-002** Deployment per bisnis (single-business), multi-cabang di dalamnya — bukan SaaS multi-tenant. **[Status 2026-10-30: SUPERSEDED untuk mode SaaS oleh ADR-020. Produk kini SaaS multi-tenant bermode ganda; ADR-002 hanya tetap menggambarkan mode instalasi individual.]**
- **ADR-003** Uang DECIMAL(15,2) + helper integer-sen; **bcmath dilarang** (insiden produksi Artesis 15 Okt 2026).
- **ADR-004** Akuntansi event-driven lewat template posting terpusat; jurnal tidak teredit, koreksi = jurnal balik.
- **ADR-005** Stok single-writer lewat `stock_movements`; saldo = gerakan terakhir.
- **ADR-006** Precal adalah turunan terhitung; PDR/PDL hanya di resep — mutlak. *(Revisi 2026-10-23: bagian "turunan terhitung" DIGANTIKAN ADR-019 — precal kini input manual per item terikat frame; larangan PDR/PDL di precal TETAP mutlak.)*
- **ADR-007** Isolasi cabang = global scope + policy + konteks aktor; UI bukan pagar. Pengecualian: master pelanggan & resep bersifat global (ADR-012). **[Catatan SaaS 2026-10-30: STILL VALID. Pagar cabang adalah fondasi yang tetap dipakai; pagar tenant adalah lapisan tambahan di atasnya yang BELUM terbangun — desain target Bagian J.2, pemenuhan Fase 1–3 Roadmap.]** **[Pembaruan 2026-11-12: lapisan pagar tenant SUDAH terbangun pada Fase 0 (PR #23–#33) — `TenantContext`/`TenantScope` di atas pagar cabang; lihat Bagian J.1/J.2 status terkini.]**
- **ADR-008** Penomoran dokumen per bulan per tipe, tidak didaur ulang, void tercatat.
- **ADR-009** WhatsApp di belakang antarmuka gateway yang dapat diganti penyedianya; async, tidak menggagalkan transaksi.
- **ADR-010** Dokumen dulu, kode kemudian: PRD → PRD Detail → Blueprint → persetujuan owner → Fase 0.
- **ADR-011** Multi-bahasa sejak Fase 0 (keputusan owner 2026-10-17): antarmuka dwibahasa Indonesia (bawaan) + Inggris. Semua teks UI lewat mekanisme lokalisasi Laravel (`lang/id`, `lang/en`, kunci `ui.*`); tidak ada string tampilan keras di Blade/controller/service. Preferensi bahasa per pengguna tersimpan di `users.locale`; tamu memakai sesi; locale di luar daftar dukungan (`config('app.supported_locales')`) ditolak. Modul baru WAJIB melengkapi kedua berkas bahasa untuk setiap kunci yang ditambahkannya. **[Perluasan 2026-10-29 (Fase 1 SaaS): bahasa dukungan menjadi EMPAT — `id, en, ms, zh` — kewajiban modul baru berlaku untuk keempat berkas; font fallback CJK disediakan untuk zh; paritas kunci semua locale diuji `ArchitectureTest` + `LocaleTest`.]**
- **ADR-012** Master pelanggan, resep & produk berlaku GLOBAL (keputusan owner 2026-10-17, sesudah gerbang MVP): pelanggan yang terdaftar di satu cabang dapat dilayani dan ditransaksikan di semua cabang, dan resepnya ikut berlaku global; produk memang sudah global sejak M.1. `customers.branch_id` = cabang pendaftaran dan `prescriptions.branch_id` = cabang pemeriksaan — keduanya atribut asal untuk atribusi, BUKAN pagar (model tidak memakai `BranchScoped`; pagarnya izin + penolakan aktor platform). Data transaksional tetap terpagar cabang penuh (ADR-007): penjualan, pembayaran, stok, PR/PO, penerimaan, transfer, jurnal, laporan. Cabang transaksi penjualan = cabang operasi aktor (atau `branch_id` eksplisit yang aktor berhak atasnya), tidak pernah cabang asal pelanggan — stok keluar dan pendapatan tercatat di cabang tempat transaksi terjadi. **[Status 2026-10-30: REQUIRES OWNER DECISION untuk mode SaaS (OD-2 di PRD). Rasional intra-bisnis ADR ini tetap sah; tetapi dalam SATU instansi bersama, "global" berarti terlihat oleh SEMUA tenant — konsekuensi kebocoran & mutasi antar-tenant dinyatakan penuh di Bagian J.3. ADR ini hanya aman apa adanya pada topologi instalasi-per-tenant.]** **[Status 2026-11-12 — OD-2 DIPUTUSKAN (10 Nov 2026): isolasi per tenant penuh. ADR ini berlaku sebagaimana dikualifikasi: "global" berarti global DI DALAM tenant (lintas cabang satu tenant); antar-tenant, master terpagar kepemilikan tenant penuh (S-02, PR #28).]**
- **ADR-013** Tata letak SIDEBAR menggantikan Varian A (keputusan owner 2026-10-18): sesudah menu aplikasi melampaui 17 tautan dan navigasi header harus dikelompokkan paksa pada perbaikan test view Fase 1, owner memutuskan navigasi berpindah ke sidebar kiri dengan klasifikasi menu & submenu (6 kelompok: Utama, Operasional, Pembelian & Persediaan, Akuntansi, Laporan, Master & Pengaturan), disetujui dari pratinjau `docs/preview/pratinjau-sidebar.html`. Kontrak yang TIDAK berubah: judul tepat satu kali (di topbar), pencarian global selalu di atas (di topbar), satu dropdown Aksi + satu Cetak per layar *(butir dropdown ini kemudian DIGANTIKAN ADR-018 — tombol sejajar)*, detail = halaman penuh, dwibahasa. Varian A dan varian tanpa header (B) sama-sama tidak dipakai lagi.
- **ADR-014** Metode costing = **FIFO per cabang** (keputusan owner 2026-10-18, gerbang F4.2): setiap penerimaan membentuk satu lapisan harga (produk, cabang, qty diterima, qty sisa, harga aktual penerimaan, referensi receipt). Setiap gerakan keluar (penjualan, transfer keluar, selisih opname kurang, stok rusak) mengonsumsi lapisan tertua cabang itu lebih dulu; `stock_movements` wajib menangkap biaya per gerakan (harga satuan lapisan + total) sehingga jurnal HPP, nilai persediaan laporan stok, dan saldo Persediaan buku besar berasal dari sumber yang sama — rekonsiliasi ketiganya adalah invariant gerbang F4.2. Retur penjualan mengembalikan lapisan pada biaya ASAL gerakan penjualannya; retur pembelian mengonsumsi lapisan receipt-nya sendiri lebih dulu; transfer antar cabang memindahkan unit beserta biaya lapisannya (cabang penerima menerima lapisan berharga sama). **Aturan penangguhan backorder:** gerakan keluar yang melampaui lapisan tersedia (lensa/rx/softlens boleh negatif) dicatat HPP-nya sebesar lapisan yang ada; kekurangan qty-nya DITANGGUHKAN dan dibebankan tepat saat penerimaan penutup tiba (alokasi penerimaan: tutupi kekurangan tertua lebih dulu, jurnal penyesuaian HPP pada tanggal penerimaan) — penerimaan sesudah itu membentuk lapisan terbuka dari sisanya. Bila kekurangan tidak pernah tertutup dan dihapus lewat opname, biaya fallback terakhir = cost_price master produk, dinyatakan eksplisit di jurnalnya. **Migrasi:** lapisan pembuka dibangun dari saldo stok per cabang berjalan dinilai pada cost_price master (aproksimasi pembuka yang dinyatakan), selisih terhadap saldo Persediaan buku besar dijurnal sebagai penyesuaian pembuka. Metode statis master & opsi rata-rata bergerak dinyatakan gugur sebagai metode berjalan sesudah migrasi ini.
- **ADR-015** Model katalog DUA TINGKAT menyamai model produk Artesis + gambar produk (keputusan owner 2026-10-18): katalog berbentuk induk → varian. Tabel baru `product_parents` (name, brand, type, is_active) menjadi induk; baris `products` yang ada menjadi VARIAN — masing-masing varian tetap memiliki SKU, barcode, harga beli/jual, dan stoknya sendiri, dan **products tetap satu-satunya unit penyimpan stok** sehingga seluruh rujukan transaksional (gerakan stok, lapisan FIFO, penjualan, PO, penerimaan, opname, garansi, label) tidak berubah. Bidang detail khas Artesis menjadi kolom nyata pada varian: lensa `sph`/`cyl`/`add`/`eye_side` (di samping atribut index/coating/diameter/lens_kind yang dipertahankan), softlens `power` (naik kelas dari atribut menjadi kolom), frame memakai `color` yang sudah ada. `products.image_path` menampung gambar per varian (tambahan baru dari owner; Artesis tidak memiliki gambar produk). Induk dibuat otomatis dari pengelompokan (type, brand, name) untuk produk lama, dan otomatis oleh service bila varian dibuat tanpa induk. Konsekuensi yang dinyatakan: matriks stok lensa (SPH×CYL per cabang) dapat dibangun dari data varian. **Keputusan owner 2026-10-19: matriks dibangun BUKAN sebagai menu** — matriks adalah alat bantu di dalam alur kerja: (1) halaman buat PO (pilih induk lensa + cabang → grid stok per kekuatan; sel terisi menjadi item PO pada harga beli master, baris sama digabung); (2) halaman hitung opname (item lensa/RX berkekuatan dikelompokkan menjadi grid per induk dengan input counts[] yang sama, item lain tetap tabel datar). Pembangunnya `LensMatrixService` (grid dari varian ber-SPH sebuah induk; sumbu SPH & CYL menurun; sel dapat memuat lebih dari satu varian, mis. sisi mata R/L). **Lanjutan keputusan owner 2026-10-19:** kategori produk menjadi entitas terpisah persis Artesis — tabel `product_categories` (name unik, description, type, is_active, sort_order) ter-seed lima kategori (Frame, Lensa, Lensa RX, Softlens, Aksesoris) yang terpetakan 1:1 ke tipe sistem; induk memegang `category_id` (FK restrict) dan `type` induk selalu diselaraskan dari kategorinya; kategori untuk induk wajib setipe. **Koreksi owner 2026-10-19 (hari yang sama): kategori TIDAK ditampilkan di menu dan tidak memiliki UI kelola** — kelima kategori bersifat tetap (tidak ditambah/dikurangi dalam pemakaian), sehingga kategori murni data: induk otomatis memegang kategori bawaan tipenya dan nama kategori tampil di detail induk. SKU tetap berada di varian (= detail pada Artesis) dan induk tidak memiliki SKU. **Penyelarasan model matriks (perintah owner 2026-10-19, sesudah owner menilai model awal tidak seperti Artesis):** `LensMatrixService` dibangun ulang mengikuti model matriks Artesis — sumbu adalah RENTANG LENGKAP langkah 0,25 (sel tanpa varian tetap tampil sebagai stok 0) dengan tiga bentuk menurut jenis lensa (`lens_kind`): SV = SPH −15,00..+6,00 × CYL −4,00..0,00; Progresif = SPH −2,00..+2,00 × ADD 0,75..3,00 dengan baris terpisah per sisi mata (R/L); Bifokal = SPH × ADD. Pemilih matriks PO membawa saringan index & coating dari varian induk. Di PO, sel kosong yang diisi jumlahnya MELAHIRKAN varian baru dari sel itu (validasi langkah 0,25 dan rentang per jenis; SKU otomatis awalan MX; harga meniru varian saudara induknya) lalu menjadi item PO — meniru PurchaseOrderLensMatrixController Artesis. Opname memakai bentuk yang sama per jenis tetapi hanya atas item snapshot. **Dashboard dinamis + pengayaan grafik laporan (perintah owner 2026-10-19):** dashboard menjadi dinamis — seluruh kartu dan grafik mengikuti periode terpilih (hari ini/7 hari/30 hari/bulan ini) lewat endpoint JSON `GET /dashboard/data` yang mengembalikan fragmen HTML server-rendered (kartu + grafik), ditukar Alpine tanpa muat ulang dan auto-refresh 60 detik; muatan awal dirender server pada periode bawaan 30 hari. Grafik digambar sebagai **SVG buatan server** oleh `App\Support\SvgChart` (line multi-seri + doughnut; tanpa pustaka luar/CDN — preseden Barcode): dashboard memuat line tren penjualan harian + doughnut komposisi penjualan per tipe produk; halaman Analitik diperkaya line tren per cabang multi-seri (bulan berkesinambungan) + doughnut komposisi pendapatan per kategori, tabel tetap di bawahnya. Seluruh angka grafik tetap terpagar izin & cabang seperti kartu.
- **ADR-016** *(Butir M2/M3/M4 DIGANTIKAN oleh ADR-017 — lihat entri berikutnya.)* Penutupan celah paritas Artesis M1–M7 (perintah owner 2026-10-19 "Kerjakan semua"; audit paritas 2026-10-18): (M1) **Penampil Log Aktivitas** membaca `audit_logs` yang sudah ada, sepagar izin `settings.manage` (bukan izin baru) dengan pagar cabang BranchContext (aktor terpagar hanya melihat log cabangnya; log tanpa cabang hanya untuk aktor global; detail di luar cakupan 403). (M2) **Limit kredit formal**: `customers.credit_limit` NULL berarti tanpa limit; SalesService menolak penjualan baru (baris pelanggan dikunci `lockForUpdate`) bila piutang beredar + sisa tak terbayar penjualan ini melampaui limit; piutang beredar adalah Σ(total−paid) penjualan aktif. (M3) **Pemakaian asuransi**: klaim BUKAN diskon — SalesService menambahkan kaki pembayaran metode `insurance` sebesar `insurance_claim_amount` ke tender Piutang Asuransi **1-1700** sehingga sisa tagihan pelanggan dan pemeriksaan limit kredit bersih dari klaim; metode `insurance` TIDAK ada di daftar pilihan manual kasir; snapshot `insurance_usages` + penerimaan bertahap `insurance_claim_receipts` (Dr tender / Cr 1-1700). (M4) **Insentif berkala**: penerima penjualan adalah `cashier_id`; aturan persen/flat per peran penerima ± produk, masa berlaku tumpang-tindih rentang; generate idempoten per (rentang, cabang, penerima) dengan kunci unik basis data; penyesuaian manual hanya saat draf; persetujuan eksplisit Owner/Admin memosting Dr Beban Insentif **5-1800** / Cr Utang Insentif **2-1600**; pembayaran Dr 2-1600 / Cr Kas/Bank. (M5) **Ekspor XLSX asli** tanpa pustaka luar: `App\Support\XlsxWriter` menulis OOXML (ZIP berisi XML, string inline, sel numerik) — dipilih agar daftar ekstensi tertutup dan larangan bcmath tetap utuh; seluruh ekspor laporan menerima `?format=xlsx` di samping CSV; grid matriks PO dapat diunduh sebagai XLSX. (M6) **Backup & restore dalam aplikasi**: snapshot DATA seluruh tabel sebagai JSON terkompresi ZIP di `storage/app/backups` (skema tetap milik migrasi); restore menghapus & mengisi ulang tiap tabel dalam satu transaksi dengan FK checks nonaktif, selalu didahului backup keselamatan berawalan `pra-restore`, khusus Owner dengan konfirmasi mengetik RESTORE, dan jejak audit ditulis sesudah restore agar tidak ikut tergantikan. (M7) **Privileged Recovery**: halaman publik `/pemulihan-akun` hanya membuatkan token broker standar + notifikasi ResetPassword untuk pengguna AKTIF berperan Owner; respons selalu generik anti-enumerasi; setiap percobaan teraudit (`recovery.requested`, email hanya sebagai hash); dibatasi throttle 3×/10 menit.
- **ADR-017** Penyelarasan ulang M2/M3/M4 ke perilaku Artesis (audit ulang atas perintah owner 2026-10-20 — owner menilai M2/M3/M4 belum sesuai Artesis; audit kode dua repo membenarkan; keputusan owner: "Ya, perbaiki ketiganya selaras Artesis"): (M2) **Kredit pelanggan menggantikan limit kredit** — Artesis TIDAK mengenal plafon piutang: fitur sebenarnya adalah voucher **CustomerCredit** yang terbit HANYA dari refund penjualan bermetode kredit toko (`store_credit`), bernomor CR-YYYYMM-NNNN, berstatus active/exhausted dengan sisa `remaining_amount`; penerapan lewat `CustomerCreditService::apply` dalam transaksi terkunci: jumlah > 0, kredit aktif & sisa cukup, pelanggan sama, dan total terbayar pelanggan (di luar kaki insurance) + jumlah terapan tidak melampaui sisa tagihan (total − klaim asuransi); pasangan (kredit, penjualan) unik; jurnal mengikuti kaki pembayaran `store_credit` (Dr Kredit Toko **2-1500** / Cr Piutang Pelanggan **1-1300**). Kolom `customers.credit_limit` dan penolakan berbasis plafon di SalesService **dicabut** (migrasi 000040). (M3) **Master asuransi ber-`claim_value`** — master `insurances` = name + `claim_value` (nilai klaim yang ditanggung, WAJIB) + is_active; UI kelola master baru di Master & Pengaturan sepagar `insurances.manage`; memilih asuransi di form penjualan mengisi klaim otomatis sebesar claim_value (cap ≤ total ditegakkan server); **penerimaan klaim bertahap dicabut seluruhnya** (tabel `insurance_claim_receipts`, layanan, halaman, dan rute) karena Artesis tidak memiliki alur penerimaan — histori pemakaian tampil sebagai **Laporan Pemakaian Asuransi** (Laporan → Pemakaian Asuransi, ekspor CSV/XLSX) dari snapshot `insurance_usages`; kolom `insurances.phone` dicabut (migrasi 000041). (M4) **Mesin insentif ditulis ulang mengikuti IncentiveService Artesis**: hanya penjualan **Lunas** (paid ≥ total) dalam bulan kalender periode oleh kasir berperan **Cabang/Admin**; basis **per item** = `SaleItem` final sesudah diskon; baris penjual + baris **kepala optik** paralel (Admin AKTIF TUNGGAL di cabang transaksi; 0 atau >1 Admin → generate gagal total fail-closed; dilewati bila Admin adalah penjualnya); rule aktif pada tanggal transaksi, override produk menang atas kolektif, urutan masa berlaku terbaru; tanpa rule → baris **cancelled** beralasan tetap dan **approve menolak** periode yang memuatnya; retur parsial menyusutkan basis **pro-rata qty**, retur penuh/kasus garansi membatalkan baris penjual tanpa baris admin; **clawback**: retur sesudah periode approved/paid membuat penyesuaian negatif pro-rata di periode **bulan berikutnya** (ledger `IncentiveAdjustmentService`, penjaga kumulatif fail-closed, idempoten per pasangan item–retur) — item historis tidak diubah; **persetujuan & pembayaran tanpa jurnal** (markPaid status-only) dan akun **2-1600 Utang Insentif / 5-1800 Beban Insentif dicabut** dari Chart & seeder; penyesuaian manual dicabut; ekspor Excel per periode ditambahkan (migrasi 000042). M1, M5, M6, M7 tidak berubah. ADR-016 butir M2/M3/M4 dinyatakan **digantikan** oleh ADR ini.
- **ADR-018** Tombol perintah SEJAJAR menggantikan dropdown Aksi/Cetak (keputusan owner 2026-10-22 — membatalkan aturan tombol terkelompok 2026-10-17 pada F.2 butir 8 dan Prinsip Tampilan PRD-Detail): owner menilai dropdown menyembunyikan perintah dari pemula — "tombol itu di semua menu jangan aksi tapi pisah saja sejajar biar lebih mudah dipahami pemula". Pola baru: seluruh perintah per layar dirender sebagai tombol tersendiri yang SELALU TERLIHAT dalam satu kelompok sejajar (komponen bersama `AksiBar` di kepala layar; `AksiBaris` untuk aksi per baris tabel); "Buat Baru" = tombol primer emerald pertama pada layar daftar; dokumen cetak = tombol bergaris emerald berikon printer dalam kelompok yang sama (tetap dibuka sebagai dokumen penuh di tab baru — E.5 tidak berubah); item berbahaya = urutan terakhir kelompok, bergaya bahaya (merah), tetap wajib konfirmasi + alasan lewat DialogKonfirmasi. Perintah yang membutuhkan isian (alasan, periode, dsb.) dibuka lewat dialog dari tombolnya; dialog tetap hanya untuk konfirmasi/aksi, bukan untuk menampilkan detail. Komponen `DropdownAksi`, `DropdownCetak`, dan `RowActions` DICABUT; `MenuItem` berubah menjadi tombol (varian primer/cetak/bahaya dan ukuran kecil untuk baris). Butir "satu dropdown Aksi + satu Cetak per layar" pada daftar kontrak ADR-013 dinyatakan digantikan oleh ADR ini; kontrak lain tidak berubah (judul satu kali, pencarian global di topbar, detail halaman penuh, dwibahasa, sidebar). Pratinjau yang menjadi dasar pola: `docs/preview/pratinjau-tombol-sejajar.html`.
- **ADR-019** POS terminal satu halaman + precal MANUAL per item (keputusan owner 2026-10-22, audit kode dua repo pada hari yang sama): owner memutuskan (1) precal ikut Artesis — "precal tidak terikat resep namun terikat frame"; (2) fitur ekstra POS Lentera dipertahankan; (3) model POS ikut Artesis penuh. Isi: (a) Layar buat penjualan menjadi **terminal kasir satu halaman** (pencarian produk & pelanggan async, keranjang, resep dimuat per pelanggan, klaim asuransi terisi otomatis dari `claim_value`, status DP/Lunas turunan dari pembayaran, footer ringkasan tetap) — transport tetap POST formulir dengan kontrak field yang sama agar kontrak server & seluruh tes penjualan tidak berubah. (b) **Precal manual 11 kunci** (`pvr, pvl, a, b, dbl, ed, vd, pa, wrap, de, type_frame`) diinput per item Lensa RX lewat panel item di terminal; `RxPrecalValidator` menegakkan numerik/`de` ∈ {R,L}/`type_frame` maks 100; boleh kosong saat jual (flag `precal_complete`; dilengkapi kemudian lewat detail penjualan, izin `sales.update`, tersinkron ke item PR tertaut); tersimpan JSON `rx_parameters` di `sale_items` (migrasi 000043, bersama snapshot resep) dan **tersalin ke `purchase_request_items`** saat PR otomatis kekurangan dibuat. (c) Item RX kini **mewajibkan resep** milik pelanggan yang sama (sebelumnya hanya `prescription_ref`); konsekuensi yang dinyatakan: penjualan RX offline tanpa resep kini masuk antrean pengecualian sinkron, bukan selesai diam-diam. (d) `PrecalService` otomatis terikat resep **DICABUT** beserta halaman & cetakan precal di resep dan tautannya — ADR-006 digantikan pada bagian "precal turunan terhitung"; larangan PDR/PDL di precal pada ADR-006 **tetap berlaku mutlak**. (e) Fitur Lentera yang Artesis tidak punya **dipertahankan** di dalam terminal: diskon transaksi, pajak `tax_mode`, pembayaran split, cicilan, dokter pemeriksa, pembayaran poin & kredit toko.
- **ADR-020** Lapisan platform SaaS & mode ganda (keputusan pemilik proyek 2026-10-29; terbangun sebagai Fase 1 SaaS M1–M6, ter-merge ke `main` s.d. `855a0c16`, CI 5/5, suite 475 test): produk adalah **SaaS multi-tenant** — tabel Platform (`plans, plan_features, tenants, tenant_subscriptions, tenant_feature_overrides, tenant_billing_requests`), `tenant_id` pada `branches`/`users`/`products`, registry fitur `PlanFeatureRegistry`, penegakan server `PlanGate` + middleware `plan.feature`, pendaftaran mandiri + provisioning panel lewat satu `TenantSignupService`, penagihan transfer manual (`BillingService`), panel Platform Superadmin bergerbang ganda (middleware `platform` + `plans.manage`/`tenants.manage`). **Semantik mode ganda adalah kontrak:** aktor bertenant digerbang Plan; aktor tanpa tenant (instalasi individual) lolos semua fitur & batas. ADR ini **tidak** mengklaim isolasi data bisnis antar-tenant — itu pekerjaan terpisah (Bagian J.2/J.3, Roadmap Fase 1–3). **[Pembaruan 2026-11-12: pekerjaan isolasi tersebut selesai pada Fase 0 (PR #23–#35, termasuk B-02 partisi gudang & B-03 ekspor per tenant); batas kepercayaan baca in-processnya diatur ADR-022.]**
- **ADR-021** Identitas produk & repositori (2026-10-29/30): nama produk **Artesis** (menggantikan nama lama di semua permukaan pengguna; penanda internal warisan — awalan komponen, nama DB uji, perintah `lentera:*` — dipertahankan sebagai penanda teknis), repositori diganti nama menjadi `toromagetan/Artesis-SaaS`. Repositori `toromagetan/Artesis` adalah proyek berbeda dan tidak terkait kode dengan repositori ini.
- **ADR-022** Batas kepercayaan baca in-process tanpa aktor (keputusan pemilik proyek 2026-11-11, Opsi A): query `TenantScoped`/`BranchScoped` in-process tanpa aktor tetap diizinkan membaca lintas tenant dengan lapisan layanan berparameter aktor sebagai penegak ember; HTTP tamu gagal-tertutup; tulis global tanpa aktor wajib konteks sistem. Dokumen penuh: [docs/adr/ADR-022-batas-kepercayaan-in-process.md](adr/ADR-022-batas-kepercayaan-in-process.md). *(Penomoran: nomor ADR-020/021 sudah dipakai entri di atas; dokumen ini adalah ADR berikutnya.)*
- **Keputusan yang semula terbuka — seluruhnya SUDAH DIPUTUSKAN:** (1) Blueprint disetujui owner & terbangun penuh; (2) payroll di **Fase 4** (terbangun); (3) ~~penyedia gateway WA (F3.4)~~ DIPUTUSKAN 2026-10-18: Meta Cloud API langsung (sementara); (4) ~~metode costing (gerbang F4.2)~~ DIPUTUSKAN 2026-10-18: FIFO per cabang (ADR-014); (5) Fase 0 dimulai atas kata owner & selesai 2026-10-17/18.

---

# BAGIAN J — ARSITEKTUR SaaS (aktual & target)

## J.1 Arsitektur SaaS Aktual (baseline awal `a96f757`; keadaan terkini sesudah Fase 0 dinyatakan pada paragraf penutup bagian ini)

Komponen yang **sudah ada** di kode:

| Komponen | Bukti di kode | Perilaku |
|---|---|---|
| Model Platform | `app/Modules/Platform/Models/`: `Tenant, Plan, PlanFeature, TenantSubscription, TenantFeatureOverride, TenantBillingRequest` | Skema migrasi 000048–000050 |
| Registry fitur | `app/Support/PlanFeatureRegistry.php` | 8 fitur boolean (`reports_advanced, roles_permissions, crm_members, purchasing_full, accounting_full, whatsapp, api_access, ai_assistant`) + 4 batas (`products_max, users_max, branches_max, report_history_days`); nilai bawaan = tangga harga ketetapan pemilik proyek |
| Layanan langganan | `PlanService` | Fitur efektif = Plan aktif + override yang belum kedaluwarsa; tanpa langganan aktif → hak Free; `assignPlan` menutup langganan lama (`expired`) + audit; `setOverride` mewajibkan alasan |
| Gerbang penegakan | `PlanGate` + middleware `EnsurePlanFeature` (alias `plan.feature:<key>`) | Aktor bertenant: fitur digerbang 403 (`ui.plans.locked`), batas digerbang di service pembuat entitas (`products_max` di `ProductService::create`, `users_max` di `UserService::createUser`, `branches_max` di `BranchController::store` — semuanya menstempel `tenant_id`). Aktor **tanpa** tenant: selalu lolos (mode individual) |
| Pendaftaran & provisioning | `TenantSignupService::register` dipakai `SignupController` (publik, `throttle:6,1`) **dan** `TenantController::store` (panel) | Satu transaksi: Tenant (slug unik berurutan) + cabang pertama + Tenant Owner + Subscription Free; kata sandi awal dari panel ditampilkan satu kali |
| Penagihan | `BillingService` + `BillingController` (tenant) + `PlatformBillingController` | Jumlah dibekukan saat pengajuan; konfirmasi platform → `assignPlan` dengan `expires_at` = hari konfirmasi + bulan; tujuan transfer dari settings platform |
| Panel platform | `PlanController, TenantController, PlatformBillingController` di grup rute bergerbang ganda | Middleware `platform` (aktor `is_platform`) **dan** izin `plans.manage`/`tenants.manage`; Owner tenant berperan `*` sekalipun menerima 403 (teruji) |
| Halaman tenant | `MyPlanController` (`GET /paket-saya`) | Tenant melihat Plan, pemakaian, dan mengajukan penagihan; aktor individual melihat kartu "Instalasi Mandiri" |
| Isolasi pengelolaan pengguna | `UserController`, `User::` scope tenant, `UserService` (perbaikan 2026-10-30) | Daftar/aksi dibatasi tenant aktor; aktor platform tidak tampil di pengelolaan pengguna tenant; cabang divalidasi per tenant (`UserTenantIsolationTest`) |
| Konteks cabang | `BranchContext` + `BranchScope` (`app/Support/Scopes/BranchScope.php`) | Global scope cabang pada model transaksional inti (`Sale, PurchaseOrder, PurchaseRequest, SaleReturn, StockMovement, StockTransfer, StockOpname, CustomerCredit`) |

Yang **belum ada** pada baseline awal (jangan dinyatakan sebaliknya untuk baseline itu): tidak ada `TenantContext`/`TenantScope`; tidak ada `tenant_id` pada tabel bisnis selain `branches`/`users`/`products`; tidak ada penegakan `tenants.is_active` di alur autentikasi; tidak ada ekspor/restore per tenant; tidak ada awalan tenant pada cache/storage/antrean.

**Keadaan terkini (baseline `43db5b54`, 2026-11-12 — sesudah Fase 0):** seluruh kekurangan di atas sudah ditutup kecuali awalan cache: `TenantContext` (lima mode: tenant/platform/standalone/unassigned/guest) + `TenantScope`/`BranchScope` menegakkan pagar tenant pada data bisnis (PR #23–#33); `tenants.is_active` ditegakkan pada login & akses (S-01); kepemilikan role kustom & settings per tenant (S-03); partisi gudang garansi per tenant (B-02, PR #34); ekspor data per tenant dengan registry klasifikasi tabel yang diuji mekanis (B-03, PR #35); backup tingkat instalasi digerbang `backups.manage` + `BackupPolicy` (tenant selalu ditolak). Restore per tenant diputuskan tidak dibangun; offsite backup belum terimplementasi; awalan tenant pada cache belum dikerjakan (Fase 6).

## J.2 Arsitektur Target — Tenant-Aware (desain target; sebagian besar SUDAH dipenuhi Fase 0 — status per butir pada catatan di akhir bagian ini)

Target ini adalah desain yang disetujui untuk dikerjakan bertahap; status pemenuhannya per baseline `43db5b54` dicatat sesudah daftar ini:

1. **TenantContext** — satu service konteks yang menyelesaikan tenant dari pengguna terautentikasi di sisi server pada awal request/job (pola yang sama dengan `BranchContext`); tidak pernah membaca tenant dari input request, subdomain, atau header yang dapat dipalsukan tanpa validasi kepemilikan.
2. **Tenant-scoped query** — `TenantScope` global pada semua model milik tenant (J.3), dengan bypass eksplisit hanya untuk aktor platform pada rute platform, dan bypass itu teraudit. Model tanpa konteks tenant yang valid pada mode SaaS harus gagal tertutup (fail-closed), bukan mengembalikan semua baris.
3. **Tenant-aware authorization** — urutan pemeriksaan tetap: autentikasi → aktor platform berhenti di gerbang platform → izin registry → Plan (`PlanGate`) → kepemilikan tenant objek → pagar cabang (`BranchContext`). Policy sumber daya memvalidasi tenant objek terhadap tenant aktor pada setiap baca/tulis (anti-IDOR antar-tenant).
4. **Tenant-scoped storage** — berkas tenant di bawah awalan `tenants/{tenant_id}/…`; unduhan selalu lewat controller berotorisasi, tidak pernah path langsung yang dapat ditebak.
5. **Tenant-scoped cache** — awalan kunci `t{tenant_id}:` untuk semua cache data bisnis; cache global hanya untuk data platform yang memang global (mis. definisi Plan).
6. **Queue & scheduler** — job membawa `tenant_id` eksplisit dan memulihkan TenantContext sebelum bekerja; scheduler mengiterasi tenant aktif dan menjalankan pekerjaan per tenant dengan konteksnya (kegagalan satu tenant tidak menghentikan tenant lain dan tercatat per tenant).
7. **Reports & exports** — pagar tenant diterapkan pada service laporan (satu titik), sehingga tampilan dan ekspor mewarisi pagar yang sama; uji kesetaraan layar vs berkas diperluas ke dimensi tenant.
8. **Audit log bertenant** — `audit_logs.tenant_id` diisi dari konteks; pembacaan log untuk tenant hanya baris tenant itu; aktor platform membaca lintas tenant hanya dari panel platform.
9. **Suspension enforcement** — middleware autentikasi menolak pengguna yang tenant-nya `is_active = false` (kecuali aktor platform), termasuk memutus sesi berjalan saat tenant dinonaktifkan.
10. **Offboarding** — ekspor penuh per tenant (J.5) → masa tenggang → penghapusan terjadwal yang terverifikasi tidak menyentuh baris tenant lain (transaksi per tabel dengan filter tenant + hitung baris sebelum/sesudah + audit platform).

**Status pemenuhan J.2 per 2026-11-12:** butir 1 ✅ (`TenantContext`), 2 ✅ (`TenantScope` + pagar layanan; batas baca in-process = ADR-022), 3 ✅ (urutan otorisasi tenant-aware ditegakkan; kontraknya di `docs/POLA-POLICY.md`), 4 ◐ (arsip ekspor per direktori tenant; awalan `tenants/{id}` untuk semua berkas unggahan belum), 5 ✗ (awalan cache belum dikerjakan), 6 ✅ (perintah terjadwal dalam konteks sistem eksplisit; layanan berparameter aktor memvalidasi ember), 7 ✅ (pagar laporan + ekspor per tenant B-03), 8 ◐ (pembacaan log terpagar cabang/konteks; kolom `tenant_id` khusus belum ada), 9 ✅ (S-01), 10 ◐ (ekspor penuh ada; penghapusan terjadwal menunggu OD-5).

## J.3 Klasifikasi Kepemilikan Data (baseline awal `a96f757`; per baseline `43db5b54` basis data memuat 89 tabel dan klasifikasi mekanis yang ditegakkan test adalah registry ekspor B-03 di `TenantExportService`)

Kelas: **P** = Platform-owned · **T** = Tenant-owned · **B** = Branch-owned (dimiliki tenant lewat cabangnya) · **G** = Global reference/master. Kolom "Scope wajib" adalah target J.2; "Keadaan" adalah fakta baseline.

| Kelas | Tabel | Scope wajib | Keadaan di baseline & risiko bila scope absen |
|---|---|---|---|
| P | `plans, plan_features` | Hanya platform menulis; tenant membaca haknya sendiri lewat PlanService | Terbangun benar. Risiko rendah |
| P | `tenants, tenant_subscriptions, tenant_feature_overrides, tenant_billing_requests` | Hanya platform + layanan SaaS | Terbangun benar |
| P (infra) | `sessions, cache, cache_locks, jobs, job_batches, failed_jobs, password_reset_tokens` | Infrastruktur; payload job tidak boleh membocorkan data lintas tenant | Tanpa awalan tenant — payload job membawa model bisnis (risiko tercampur saat konteks dipulihkan manual) |
| T | `branches` | `tenant_id` aktor | **Sudah bertenant** (000048). Risiko tersisa: layar Master yang masih membaca lintas instalasi |
| T | `users, user_role, user_branch` | `users.tenant_id` aktor | **Sudah bertenant** untuk pengelolaan (2026-10-30) |
| T (target) | `roles, role_permission` | Per tenant (klon dari templat global saat tenant lahir) | **Selesai S-03 (PR #27):** role kustom milik tenant; role bawaan = templat global yang dirujuk pengguna (baca-saja bagi tenant) |
| T (target) | `settings` | Per tenant (`tenant_id` + key unik per tenant) | **Selesai S-03 (PR #27):** baris bertenant per tenant; baris global tersisa khusus konfigurasi instalasi |
| T (target) | `wa_gateways, wa_templates, ai_settings` | Per tenant | **Masih satu per instalasi** — kredensial & nomor pengirim dipakai bersama (keputusan OD-4) |
| T | `api_tokens, offline_devices, offline_exceptions` | Token/perangkat terikat pengguna tenant; gerbang Plan `api_access` | Token sudah menolak pembuat tenant tanpa `api_access`; selain itu terikat pembuatnya |
| B | `sales, sale_items, sale_payments, sale_installments, sale_returns, sale_return_items, lens_processes, qc_checks` | Tenant (lewat cabang) + cabang aktor | Terpagar **cabang** (`BranchScope` pada induk dokumen); pagar tenant belum ada — cabang tenant lain dalam satu instansi hanya tertahan oleh penugasan `user_branch`, bukan oleh kepemilikan tenant |
| B | `purchase_requests, purchase_request_items, purchase_orders, purchase_order_items, receipts, receipt_items, purchase_payments, purchase_payment_allocations, purchase_returns, purchase_return_items` | Tenant + cabang | Sama seperti di atas (PR/PO terpagar cabang) |
| B | `stock_movements, stock_layers, stock_layer_consumptions, stock_transfers, stock_transfer_items, stock_opnames, stock_opname_items` | Tenant + cabang | Terpagar cabang pada dokumen induk/gerakan; pagar tenant belum ada |
| B | `accounts, journals, journal_lines, period_locks, fixed_assets, payrolls, commissions` | Tenant + dimensi cabang pada baris jurnal | Terpagar lewat konteks aktor & izin; kepemilikan tenant belum menjadi pagar query |
| B | `customers` *(lihat catatan ADR-012)*, `prescriptions` | Tenant (global di dalam tenant) | **Selesai S-02 (PR #28):** terpagar `tenant_id`; global hanya lintas cabang satu tenant (OD-2) |
| B | `products, product_parents, product_attributes` | Tenant | **Selesai S-02 (PR #28):** terpagar `tenant_id` penuh |
| B | `suppliers, insurances, doctors, insurance_usages, customer_credits, customer_credit_applications` | Tenant (lewat konteks aktor) | **Selesai S-02 + PR #30:** master per tenant; `CustomerCredit` terpagar cabang + tenant |
| B | `membership_levels, points_ledger, member_portal_tokens, fitting_measurements, warranty_cases, warranty_replacements, broken_stocks, warranty_payments, incentive_rules, incentive_periods, incentive_items, incentive_adjustments` | Tenant + cabang terkait | Terpagar konteks aktor/izin; pagar tenant belum ada |
| B/P | `wa_messages, wa_campaigns` | Tenant + cabang pengirim | Pesan terkait pelanggan/cabang; gateway-nya sendiri masih per instalasi |
| P/B | `audit_logs` | Wajib `tenant_id` (target); baca terpagar tenant | Hanya `branch_id` saat ini — pembacaan log pada instansi bersama dapat mencampur tenant |
| P | `backups` | Hanya platform/instalasi (J.5) | **Selesai:** rute digerbang `backups.manage` + `BackupPolicy` berbasis mode — tenant selalu ditolak pada instansi bersama |
| G | `product_categories` | Referensi tetap (5 kategori, tanpa UI kelola — ADR-015) | Aman sebagai referensi global: tidak memuat data tenant |
| G (bawaan) | `qc_point_templates` | Templat bawaan; target: dapat disalin per tenant | Saat ini per instalasi |
| G (sistem) | `document_counters` | Target: per tenant (nomor dokumen tidak bocor lintas tenant) | Per instalasi — urutan nomor antar-tenant terseling (kebocoran volume kecil, bukan data) |
| Warisan | `lab_orders` (fitur dicabut 2026-10-18), `insurance_claim_receipts` (alur dicabut ADR-017) | — | Tabel peninggalan fitur yang dicabut; tidak dipakai alur berjalan. Penanganannya (biarkan/drop) = keputusan terpisah di luar dokumen ini |

**Konsekuensi ADR-012 bila dipertahankan dalam satu instansi bersama (dinyatakan eksplisit, sesuai kewajiban audit):** pelanggan, resep, dan produk yang "global" berarti (1) **kebocoran baca** — staf tenant A dapat mencari & membuka pelanggan beserta riwayat resep tenant B; (2) **mutasi silang** — pengguna berizin di tenant A dapat mengubah master yang dipakai tenant B (harga produk, data pelanggan), merusak data bisnis pihak lain tanpa jejak kepemilikan; (3) **transaksi silang** — penjualan dapat menempel pada pelanggan/produk tenant lain, mencampur stok, jurnal, dan laporan kedua pihak. Karena itu ADR-012 hanya koheren pada topologi instalasi-per-tenant; pada topologi satu instansi, Fase 2 Roadmap (isolasi master per tenant) adalah syarat mutlak, dan mempertahankan ADR-012 apa adanya di topologi itu = menolak persyaratan isolasi PRD Bagian 6. **[Resolusi 2026-11-12: risiko di atas sudah ditutup — OD-2 diputuskan per tenant penuh (10 Nov 2026) dan isolasi master per tenant selesai di S-02; paragraf ini dipertahankan sebagai catatan risiko historis yang mendasari keputusan tersebut.]**

## J.4 Arsitektur Keamanan

| Area | Keadaan terbukti (baseline) | Target / gap (Roadmap) |
|---|---|---|
| Authentication | Sesi Laravel, hash bawaan, `Password::defaults()`; pemulihan istimewa Owner-only generik + throttle + audit (ADR-016 M7) | Verifikasi email pendaftaran & CAPTCHA (Fase 4) |
| Authorization | Registry izin + policy + middleware izin; matriks keamanan 6 kasus per modul di CI | Urutan pemeriksaan tenant-aware J.2 butir 3 (Fase 1) |
| Tenant isolation | **Terbukti (Fase 0, PR #23–#35):** platform, pengguna, dan data bisnis terisolasi per tenant; matriks test keamanan per modul di CI | Pengerasan lanjutan & pembuktian menyeluruh (Fase 6) |
| Branch isolation | `BranchScope` + `BranchContext`; `branch_id` request tidak dipercaya | Tetap; validasi cabang ∈ tenant aktor sudah ditegakkan Fase 0 |
| IDOR prevention | Policy + pagar cabang **dan tenant** pada objek; test IDOR antar-cabang & antar-tenant per modul | Matriks menyeluruh per area PRD Bagian 6 (Fase 6 / D-02) |
| Privilege separation | Aktor `is_platform` ditolak dari bisnis (403 teruji); panel digerbang ganda | Tetap; audit berkala akses platform (Fase 6) |
| Session handling | Driver sesi Laravel; pengguna nonaktif ditolak | Penolakan tenant tersuspensi **sudah ditegakkan** (S-01) |
| Tenant suspension | Flag `tenants.is_active` + alih panel | **Penegakan Implemented (S-01)** — login & akses ditolak |
| Backup security | Snapshot terverifikasi; restore platform/instalasi individual + `pra-restore`; ekspor per tenant teraudit | Offsite backup (belum terimplementasi — menunggu kredensial penyimpanan eksternal) |
| File security | Disk lokal terstruktur; dokumen lewat rute berotorisasi; arsip ekspor per direktori tenant | Awalan per tenant untuk seluruh berkas unggahan (Fase 6) |
| Queue security | Job async; kegagalan tercatat `failed_jobs` | Konteks sistem eksplisit pada perintah terjadwal; pagar ember layanan pada job (ADR-022) |
| Audit trail | `audit_logs` hanya-INSERT dari aplikasi; nilai rahasia disaring | Kolom `tenant_id` + pembacaan terpagar (Fase 3) |
| Rate limiting | Throttle pendaftaran `6,1`; throttle pemulihan `3/10mnt` | Tinjau ulang throttle per tenant untuk API/kampanye (Fase 6) |
| Security monitoring | Dashboard memperingatkan `failed_jobs` & backup basi/rusak untuk pemegang `settings.manage` | Peringatan per tenant + panel kesehatan platform (Fase 6) |

## J.5 Arsitektur Backup, Ekspor & Pemulihan

Empat hal yang **berbeda** dan tidak boleh dicampur:

1. **Platform-wide disaster recovery backup** — snapshot seluruh database instalasi (mekanisme `BackupService` saat ini: dump semua tabel → ZIP-JSON terverifikasi, retensi 14, offsite milik operator). Hanya untuk operator platform/instalasi. Pada topologi instalasi-per-tenant, snapshot ini secara alami hanya memuat satu tenant.
2. **Tenant-specific data export** — ekspor data SATU tenant (semua tabel milik tenant itu, terfilter kepemilikan) yang dapat diminta Tenant Owner dan dihasilkan sistem tanpa menyentuh data tenant lain. **Implemented (B-03, PR #35):** `TenantExportService` dengan registry klasifikasi tabel eksplisit (89 tabel terklasifikasi; test gagal bila ada tabel tak terklasifikasi), kolom kredensial dikecualikan, arsip per direktori tenant, unduhan & penolakan teraudit.
3. **Tenant-specific restore** — **diputuskan TIDAK dibangun** (keputusan pemilik proyek 11 Nov 2026): restore tetap whole-DB tingkat platform/instalasi individual. Tenant Owner **tidak pernah** boleh mengakses atau memulihkan snapshot global (butir 1) — snapshot itu memuat tenant lain.
4. **Operational backup** — backup terjadwal harian sebagai jaring pengaman operasional instalasi (bagian dari butir 1, dijalankan scheduler 02:00).

Aturan otorisasi (Implemented): rute backup tingkat instalasi digerbang izin `backups.manage` + `BackupPolicy` berbasis mode (aktor platform pada instansi bersama; Owner pada instalasi individual sejati; aktor tenant selalu ditolak pada instansi bersama); ekspor per tenant digerbang `TenantExportPolicy` (Owner tenant untuk tenantnya sendiri; platform wajib `backups.manage`); setiap unduhan backup/ekspor teraudit (siapa, kapan, tenant apa); integritas diverifikasi keterbacaan + penanda kepemilikan arsip sebelum unduhan dilayani; restore selalu didahului backup keselamatan.

**Kompatibilitas format arsip (dibekukan):** payload backup & ekspor memuat penanda format `app: lentera`. Nilai itu adalah **identifier format arsip warisan** yang dipertahankan demi kompatibilitas dengan arsip yang sudah terbit — BUKAN identitas produk aktif (produk ini adalah Artesis, ADR-021). Jangan mengubah konstanta format tersebut hanya karena nama produk berubah; perubahan nilai itu adalah perubahan format yang mematahkan pembacaan arsip lama.

---
*Akhir Blueprint v3.0 — menggantikan v2.0. Perubahan berikutnya hanya lewat revisi ber-commit di repositori ini.*
