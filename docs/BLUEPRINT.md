# BLUEPRINT v2.0 — LENTERA: Arsitektur Sangat Detail & Roadmap Pembangunan

| | |
|---|---|
| Dokumen | Blueprint teknis — menggantikan Blueprint v1.0 secara penuh |
| Produk | **Lentera** — Sistem Manajemen & POS Toko Optik, kepemilikan penuh |
| Tanggal | 17 Oktober 2026 |
| Status | Draf untuk persetujuan owner — **belum ada kode; gerbang blueprint berlaku** |
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
| Frontend | Blade + Alpine.js; Livewire hanya untuk POS & Antrean Lab | Tanpa SPA; tanpa build-step wajib untuk halaman biasa |
| CSS | Tailwind (build lokal, di-commit hasilnya tidak — lewat pipeline CI) | Palet emerald + emas sebagai token desain |
| Antrean | Database queue (tanpa Redis) | Kesederhanaan operasional untuk satu bisnis |
| Penyimpanan berkas | Disk lokal terstruktur `storage/app/documents` | Dokumen hasil generate (PDF) & unggahan |
| PDF | Dompdf | Ukuran A5/A4 + thermal lewat CSS print |
| Ekspor | PhpSpreadsheet (xlsx) + CSV native | Menghormati filter & pagar cabang |

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

## B.1 Identity & Master
- **branches**: `name VARCHAR(100)` (tanpa kolom kode — keputusan terkunci), `address TEXT NULL`, `phone VARCHAR(30) NULL`, `is_active BOOL default 1`. Indeks: `name`.
- **users**: `name`, `username VARCHAR(50) UNIQUE`, `email VARCHAR(150) UNIQUE NULL`, `password` (hash), `phone NULL`, `locale VARCHAR(5) default 'id'` (preferensi bahasa — ADR-011), `is_active`, `is_platform BOOL default 0` (aktor teknis — bukan pelaku bisnis), `last_login_at NULL`.
- **roles**: `name VARCHAR(50) UNIQUE` (Owner, Admin, Kasir, Gudang, Cabang, Refraksionis, Teknisi), `label`. **permissions**: `name VARCHAR(100) UNIQUE` (registry Bagian C), `group_name`. **role_permission** (role_id, permission_id) PK komposit. **user_role** (user_id, role_id). **user_branch** (user_id, branch_id) = cabang yang dikelola; kosong + peran Owner = akses global.
- **suppliers**: `name`, `contact_person NULL`, `phone NULL`, `email NULL`, `address NULL`, `is_active`. **insurances**: `name`, `phone NULL`, `is_active`. **doctors**: `name`, `clinic NULL`, `phone NULL`, `commission_rate DECIMAL(5,2) default 0` (%), `is_active`.
- **settings**: `key VARCHAR(100) UNIQUE`, `value TEXT NULL` — pasangan kunci-nilai untuk Pengaturan Umum (identitas toko, tarif PPN bawaan, mode PPN, ukuran kertas bawaan, format tanggal). Nilai yang bersifat rahasia (kredensial gateway) TIDAK di sini — lihat `wa_gateways`.

## B.2 Catalog, CRM, Clinical
- **products**: `sku VARCHAR(50) UNIQUE`, `type ENUM('frame','lens','rx','softlens','accessory')`, `name VARCHAR(150)`, `brand VARCHAR(80) NULL`, `color VARCHAR(50) NULL`, `cost_price DECIMAL(15,2)` (modal), `sale_price DECIMAL(15,2)`, `is_active`. Indeks: `(type, is_active)`, `name`.
- **product_attributes**: `product_id FK`, `key VARCHAR(60)`, `value VARCHAR(150)` — atribut per tipe: frame (`eye_size, bridge_size, temple_size`), lensa (`index, coating, diameter, lens_kind`), rx (`sph_min, sph_max, cyl_min, cyl_max`), softlens (`power, wear_period, expiry_tracked BOOL`). Indeks `(product_id, key)` unik.
- **customers**: `name`, `phone VARCHAR(30)` (nomor WA utama), `email NULL`, `address NULL`, `birth_date DATE NULL` (untuk otomasi ulang tahun), `branch_id FK` (cabang pendaftaran — atribut asal, BUKAN pagar), `insurance_id FK NULL`, `membership_level_id FK NULL`, `is_active`. Indeks: `phone`, `name`. **MASTER GLOBAL (ADR-012): berlaku di semua cabang; pagarnya izin `customers.*`, bukan cabang.**
- **prescriptions** (Clinical): `customer_id FK`, `branch_id FK` (cabang pemeriksaan — atribut asal, BUKAN pagar; **resep GLOBAL mengikuti pelanggan, ADR-012**), `examined_at DATE`, `examiner_id FK→users NULL`, per mata (prefix `od_`, `os_`): `sph DECIMAL(5,2)`, `cyl DECIMAL(5,2)`, `axis SMALLINT (0–180)`, `add DECIMAL(5,2) NULL`, **`pdr DECIMAL(4,1) NULL`, `pdl DECIMAL(4,1) NULL`** (tempat satu-satunya PD per mata), `pd_total DECIMAL(4,1) NULL`, `prism VARCHAR(30) NULL`, `notes TEXT NULL`, `used_at TIMESTAMP NULL` (terisi saat pertama dipakai transaksi → resep terkunci dari edit). Indeks `(customer_id, examined_at)`.
- **Precal bukan tabel** — nilai turunan terhitung dari resep oleh `PrecalService` (11 parameter OD/OS: SPH, CYL, AXIS, ADD + turunan hitung tepi/centre yang dirumuskan di spesifikasi Clinical, Bagian D.5) dan **dilarang memuat PDR/PDL** dalam struktur data, tampilan, maupun cetakan apa pun.

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
- **stock_movements**: `product_id FK`, `branch_id FK`, `reason ENUM('initial_stock','sale','sale_void','receipt','purchase_return','transfer_out','transfer_in','sale_return','opname','correction')`, `qty INT` (bertanda: masuk +, keluar −), `reference_type VARCHAR(60)`, `reference_id BIGINT` (dokumen sumber), `balance_after INT` (saldo cabang-produk sesudah gerakan ini), `created_by FK`, `created_at`. Indeks `(product_id, branch_id, created_at)`, `(reference_type, reference_id)`. Baris tidak pernah di-UPDATE/DELETE (ditegakkan policy + tidak ada rute/service untuk itu). Saldo stok resmi = nilai `balance_after` gerakan terakhir per (produk, cabang) — satu-satunya sumber kebenaran stok; tabel ringkasan cache diperbolehkan hanya bila dapat dibangun ulang penuh dari movements dan lulus uji kesetaraan.
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
- **wa_gateways**: `provider VARCHAR(50)`, `sender_number VARCHAR(30)`, `credentials_encrypted TEXT` (terenkripsi Laravel `encrypted` cast; tidak pernah ditampilkan penuh di UI/log), `is_active`. **wa_templates**: `key VARCHAR(60) UNIQUE` (`invoice, pickup_reminder, after_service, birthday, campaign`), `body TEXT` (variabel `{nama}`, `{nomor}`, `{total}`, `{cabang}`), `approved_by FK NULL` (template campaign WAJIB). **wa_messages**: `customer_id FK NULL`, `phone VARCHAR(30)`, `template_key NULL`, `body TEXT`, `direction ENUM('out','in')`, `status ENUM('queued','sent','failed','delivered')`, `trigger VARCHAR(60)` (`manual|invoice|reminder|campaign|birthday`), `sent_by FK NULL`, `created_at`. **wa_campaigns**: `name`, `segment JSON` (kriteria penerima), `template_id FK`, `recipient_count INT`, `approved_by FK`, `status ENUM('draft','approved','sending','done','cancelled')`.
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
- **platform**: `users.manage, roles.manage, branches.manage, settings.manage, audit.view, backups.manage, api_tokens.manage`
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
- Service: `PrescriptionService.create` (mengunci begitu `used_at` terisi), `PrecalService.compute(prescription)` → 11 parameter per mata (SPH, CYL, AXIS, ADD, dan turunan: daya efektif vertex, ketebalan tepi estimasi per indeks lensa, diameter minimum blank, decentrasi, daya total mata, status kelayakan RX, ringkasan pasangan) — **struktur hasil dilarang memuat kunci PDR/PDL/PD dalam nama atau nilai apa pun** (diuji otomatis: pemindaian kunci pada output & cetakan precal).
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
Setiap konteks dokumen memiliki **tepat satu dropdown Cetak** di layar detailnya yang memuat seluruh dokumen konteks itu (dokumen belum ada → dibuat lalu dibuka di tab baru; sudah ada → dibuka) — tidak ada tombol cetak yang berdiri sendiri-sendiri.

# BAGIAN F — PETA RUTE & KONVENSI UI

## F.1 Peta rute (pola REST per sumber daya; semua di belakang auth)
- `GET /dashboard` · Penjualan: `GET/POST /sales`, `GET /sales/{id}`, `POST /sales/{id}/payments`, `POST /sales/{id}/void`, `GET/POST /sales-returns` · Pelanggan: `resource /customers` + `GET /customers/{id}` · Resep: `GET/POST /customers/{customer}/prescriptions`, `GET /prescriptions/{id}/precal`, `GET /prescriptions/{id}/print` · Katalog: `resource /products` per tipe lewat query `type` · Lab: `GET /lab/queue`, `POST /lab/{process}/assign`, `POST /lab/{process}/qc`, `POST /lab/{process}/complete`, `resource /lab-orders` · Pembelian: `resource /purchase-requests` (+`approve`, `convert`), `resource /purchase-orders` (+`send`, `void`), `GET/POST /purchase-orders/{po}/receipts`, `GET/POST /payments` (tunggal), `GET/POST /collective-payments`, `resource /purchase-returns`, `resource /suppliers` · Stok: `GET /stocks`, `GET /stock-movements`, `resource /stock-transfers` (+`receive`, `cancel`), `resource /stock-opnames` (+`approve`), `GET/POST /initial-stock-imports` (preview → commit) · Akuntansi: `resource /accounts`, `GET /journals`, `GET /journals/{id}`, `POST /journals/{id}/reverse`, `GET /ledger`, `GET /financial-reports/{type}`, `POST /periods/{period}/lock|unlock`, `resource /fixed-assets`, `resource /payrolls` · Membership: `GET /members`, `POST /points/adjust`, portal publik `GET /m/{token}` · Laporan: `GET /reports/{sales|purchases|stocks|analytics}` + `GET /reports/{type}/export` · Komunikasi: `GET /wa/inbox`, `POST /wa/send`, `resource /wa/templates`, `resource /wa/campaigns` (+`approve`) · Pengaturan: `resource /branches`, `resource /users`, `resource /roles`, `GET/PUT /settings`, `GET /audit-logs`, `GET/POST /backups`, `resource /api-tokens` · API: `GET /api/v1/...` read-only ber-token (F4).

## F.2 Konvensi UI bersama (mengikat semua modul)

### F.2.0 Bahasa Desain — Clean, Modern, Elegan (keputusan owner 2026-10-17)
Token desain dikunci di satu tempat (variabel tema Tailwind) dan dipakai semua modul tanpa pengecualian:
- **Warna:** emerald pekat = warna utama (aksi primer, tautan, keadaan aktif); emas = aksen terbatas (maksimal satu elemen penekanan per layar: tombol utama atau penanda aktif); latar dominan netral terang (putih/abu sangat muda); teks abu pekat. Dilarang menambah warna dekoratif di luar token.
- **Tipografi:** Plus Jakarta Sans; skala 12/14/16/20/24/32; angka uang & tabel memakai `font-variant-numeric: tabular-nums`.
- **Bentuk & ruang:** radius sudut 8–12px; bayangan maksimal satu tingkat sangat tipis; spasi mengikuti skala 4px (4/8/12/16/24/32/48); garis pemisah 1px abu muda.
- **Komponen bersama (satu implementasi, dipakai semua modul):** PageHeader, FilterBar, DataTable (sort di header), FormField (label + error), Badge status, Tombol (primer emas/emerald, sekunder garis, bahaya merah teks), **DropdownAksi** (satu-satunya wadah perintah per layar), **DropdownCetak** (satu-satunya wadah pilihan dokumen per layar), **GlobalSearch** (selalu di bagian atas; hasil terpagar cabang & izin, dikelompokkan per jenis), Kartu ringkasan, EmptyState, DialogKonfirmasi.
- **Gerak:** transisi 120–200ms pada hover/fokus saja; tanpa animasi dekoratif.
- **Detail = halaman penuh (kontrak):** setiap sumber daya memiliki rute detail sendiri yang me-render halaman penuh; **dilarang** pola preview untuk detail — tanpa modal detail, drawer/slide-over, quick-view, atau popover berisi detail. Klik pada baris/nomor di daftar selalu `GET` ke rute detail. Dialog hanya untuk konfirmasi aksi (dengan alasan bila wajib), bukan untuk menampilkan isi.
1. Daftar: header halaman bersama (judul + tombol aksi utama kanan), bar filter (pencarian, cabang bila global, rentang tanggal, status), tabel dengan **header kolom dapat diklik untuk mengurutkan** (bawaan: terbaru di atas untuk daftar bertanggal), paginasi 25/baris bawaan, keadaan kosong yang menjelaskan + tombol buat.
2. Detail: kepala ringkas, tab/bagian per sub-entitas, satu **dropdown Cetak** per konteks dokumen, dan seluruh perintah di dalam satu **dropdown Aksi** (aksi berbahaya void/nonaktif di kelompok bawah dropdown dengan pemisah, warna merah, konfirmasi + alasan wajib).
3. Formulir: kesalahan validasi tampil di samping field ( Bahasa Indonesia), tidak pernah halaman error mentah; semua pengiriman yang gagal di server mengembalikan pengguna ke formulir dengan isian utuh.
4. POS: footer ringkasan pembayaran **tetap** (fixed) di bagian bawah layar kasir; antrean lab: penyegaran berkala tanpa muat ulang penuh.
5. Pesan sistem memakai istilah bisnis (bukan istilah teknis): mis. "Periode 2026-09 sudah ditutup" — bukan nama pengecualian.
6. Aksesibilitas dasar: label form eksplisit, kontras teks memenuhi WCAG AA pada teks isi.
7. **Detail halaman penuh, bukan preview:** mengikuti F.2.0 — tidak ada implementasi detail dalam modal/drawer/quick-view di modul mana pun; uji penerimaan UI memeriksa bahwa setiap tautan detail menavigasi ke halaman penuh dan tidak ada komponen preview-detail yang terdaftar di inventaris komponen bersama.
9. **Judul satu kali + pencarian global di atas (keputusan owner 2026-10-17, tata letak direvisi 2026-10-18 — ADR-013):** judul halaman dirender tepat satu kali — dilarang ganda di topbar dan body. Tata letak resmi = **SIDEBAR** (menggantikan Varian A header ramping yang dipilih 2026-10-17; owner menilai sidebar lebih modern dan skalabel sesudah menu melampaui 17 tautan): navigasi adalah sidebar kiri emerald pekat dengan menu terklasifikasi 6 kelompok (Utama; Operasional; Pembelian & Persediaan; Akuntansi; Laporan; Master & Pengaturan) dan submenu buka-tutup; setiap item terpagar izin seperti sebelumnya. Topbar ramping memegang judul halaman (tepat satu kali — body tidak mengulang judul) dan `GlobalSearch` selalu hadir di topbar dengan endpoint pencarian tunggal yang menerapkan pagar cabang + izin sebelum hasil dikembalikan. Di layar sempit sidebar menjadi panel geser (khusus navigasi; detail tetap halaman penuh). Pratinjau yang disetujui owner: `docs/preview/pratinjau-sidebar.html`.
8. **Tombol terkelompok — satu dropdown per kelompok fungsi (keputusan owner 2026-10-17):** dalam satu layar hanya ada tepat satu `DropdownAksi` (berisi seluruh perintah layar itu, termasuk "Buat Baru" sebagai item pertama pada layar daftar, dan aksi per baris pada tabel sebagai satu dropdown per baris) dan tepat satu `DropdownCetak` (berisi seluruh dokumen konteks itu). Tidak ada tombol aksi/cetak lain yang dirender berdiri sendiri; pengecualian hanya navigasi murni (Kembali, paginasi, tautan baris ke detail). Item berbahaya berada di kelompok bawah dropdown dengan pemisah + gaya bahaya + konfirmasi. Uji penerimaan UI memeriksa jumlah elemen tombol per layar terhadap aturan ini.

# BAGIAN G — PENGUJIAN, CI, DEPLOY, BUKTI

## G.1 Strategi pengujian (per modul, semua WAJIB)
1. **Test perilaku (Feature, HTTP nyata):** alur sukses + penolakan utama per operasi tulis di Bagian D. Test memakai kemampuan aplikasi yang sah (peran/izin dari registry) — dilarang menyisipkan baris izin manual ke DB sebagai jalan pintas.
2. **Matriks keamanan per modul (kasus tetap):** (a) aktor berizin lolos; (b) aktor tanpa izin ditolak 403; (c) aktor cabang lain ditolak pada baca & tulis sumber daya cabang tetangga (IDOR lewat URL langsung); (d) aktor `is_platform` ditolak pada aksi bisnis; (e) pengguna dinonaktifkan ditolak di semua rute; (f) izin dicabut → request berikutnya ditolak.
3. **Test rekonsiliasi uang/stok (modul finansial):** invariant diuji sebagai properti — Σalokasi = pembayaran; Σdebit = Σkredit; saldo stok = gerakan terakhir; total layar laporan = total berkas ekspor.
4. **Test injeksi kegagalan:** pembayaran kolektif digagalkan di tengah alokasi (mock) → nol baris pembayaran/alokasi/jurnal tertinggal; impor stok awal berisi baris rusak → nol baris terimpor pada mode commit tanpa pratinjau bersih.
5. **Test turunan precal:** output & cetakan precal dipindai — tidak ada kunci/nilai PDR/PDL.
6. Target cakupan: service bisnis inti ≥ 90% baris; keseluruhan suite hijau adalah gerbang, bukan rata-rata.
7. **Uji regresi & konkurensi — SELALU (instruksi owner 2026-10-17):** setiap perubahan yang menyentuh hitungan uang/stok — dan setiap gerbang fase tanpa kecuali — WAJIB menjalankan (a) **suite regresi penuh** (seluruh test yang pernah ditulis, bukan hanya test modul yang berubah) dan (b) **suite konkurensi** khusus alur keuangan/stok dengan skenario tetap: dua submit bersamaan pada operasi yang sama tercatat tepat satu kali (idempotensi); dua pembayaran bersamaan pada tagihan yang sama tidak pernah melampaui nilai tagihan dan alokasi FIFO tetap konsisten; dua penjualan bersamaan atas unit stok terakhir tidak pernah menghasilkan stok negatif (satu transaksi menang, sisanya gagal bersih); pembayaran kolektif yang dijalankan dua kali bersamaan tidak menghasilkan alokasi ganda; penyelesaian (Complete) proses yang sama secara bersamaan tercatat tepat satu kali; dua penerimaan bersamaan pada satu PO tidak pernah melampaui qty PO secara kumulatif. Suite konkurensi berjalan terhadap MySQL nyata (bukan SQLite) dengan proses/transaksi paralel dan kunci baris, dan invariant G.1.3 diperiksa sesudah setiap skenario. Kegagalan satu skenario pun = gerbang GAGAL, pekerjaan berhenti dan dilaporkan — tidak ada "lolos bersyarat" untuk hitungan keuangan.

## G.2 Pipeline CI (GitHub Actions, sejak Fase 0)
Job berurutan, semua wajib hijau untuk merge: (1) **lint & format** (Pint); (2) **pindai statis** — pola terlarang: pemanggilan `bc*`, pemakaian float untuk uang di service (daftar pola di repo), string izin di luar registry, query tanpa pagar cabang pada controller daftar (daftar periksa dibantu uji arsitektur PHPUnit `ArchitectureTest`); (3) **Unit**; (4) **Feature + Security matrix**; (5) **build aset** (Tailwind) tanpa meng-commit hasil build. Aturan cabang: PR ke `main`, merge hanya sesudah CI hijau + review; tidak ada push langsung ke `main`.

## G.3 Blueprint Deploy (per instalasi bisnis)
- Layout server: `/home/<deploy-user>/Lentera` (clone pull-only), PHP-FPM 8.3, MySQL 8, nginx vhost HTTPS (sertifikat otomatis), antrean database dijalankan supervisor (`queue:work`), scheduler cron `schedule:run` (penyusutan, kedaluwarsa poin, pengingat, backup).
- Langkah rilis baku: catat HEAD lama sebagai **jangkar rollback** → pull fast-forward → `composer install --no-dev` → `migrate --force` (hanya migrasi yang sudah lulus CI) → `view:clear` → **tanpa** `config:cache/route:cache/optimize` sampai ada langkah verifikasi cache tersendiri yang disetujui owner (aturan berdiri warisan insiden Artesis) → verifikasi pasca-deploy: HEAD sesuai, health check HTTP, grep kontrak kritis (mis. nol pemanggilan bc*, catch `\Throwable` pada controller pembayaran), porcelain baseline.
- **Paritas lingkungan (gerbang sebelum rilis pertama & setiap perubahan server):** `php -v` = 8.3.x; `php -m` memuat semua ekstensi daftar A.1; versi MySQL 8.0; checklist ditandatangani di laporan rilis.
- Rollback: `git reset --hard <jangkar>` + `view:clear`; migrasi yang tidak kompatibel mundur dilarang masuk rilis tanpa rencana rollback data tertulis.

## G.4 Standar Bukti Rilis (mengikat, warisan pengerasan Artesis)
Setiap laporan merge/deploy WAJIB melampirkan output mentah yang dapat ditanya ulang: identitas merge commit & waktu merge dari API GitHub, ID run CI + status + angka test/assertion persis, HEAD server sebelum/sesudah, reflog server, hasil grep verifikasi, dan kode HTTP kesehatan. Narasi tanpa lampiran = tidak lulus audit. Klaim waktu harus koheren dengan urutan peristiwa (gerbang CI selesai sebelum deploy dimulai).

# BAGIAN H — ROADMAP SANGAT DETAIL (tugas bernomor, dependensi, gerbang keluar)

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
- [M.2] CRM + Clinical: pelanggan, resep (grid OD/OS), PrecalService + test pemindaian PDR/PDL → F0.
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
- [F3.4] Gateway WA: antarmuka + penyedia resmi + antrean/coba-lagi + log → persetujuan owner atas penyedia.
- [F3.5] Pengiriman invoice & reminder otomatis (pengambilan, after-service, ulang tahun) → F3.4.
- [F3.6] Membership: level, poin (earn/redeem/expire + jurnal liabilitas), follow-up → F3.1.
- [F3.7] Portal member ber-token → F3.6, F3.4.
- [F3.8] Inbox dua arah + kampanye berpagar persetujuan → F3.4.
**Gerbang keluar F3:** skenario DP+split+cicilan rekonsiliasi penuh di jurnal & laporan; pesan WA tercatat 100% di log; poin seimbang (liabilitas = Σ ledger poin aktif).

## Fase 4 — Inventaris Lanjut, Analitik, API
- [F4.1] Opname terjadwal ujung-ke-ujung → MVP.
- [F4.2] **Gerbang costing**: audit metode berjalan → laporan pilihan (tetap rata-rata vs FIFO) → keputusan owner tertulis → implementasi metode terpilih + migrasi nilai persediaan.
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
- **ADR-002** Deployment per bisnis (single-business), multi-cabang di dalamnya — bukan SaaS multi-tenant.
- **ADR-003** Uang DECIMAL(15,2) + helper integer-sen; **bcmath dilarang** (insiden produksi Artesis 15 Okt 2026).
- **ADR-004** Akuntansi event-driven lewat template posting terpusat; jurnal tidak teredit, koreksi = jurnal balik.
- **ADR-005** Stok single-writer lewat `stock_movements`; saldo = gerakan terakhir.
- **ADR-006** Precal adalah turunan terhitung; PDR/PDL hanya di resep — mutlak.
- **ADR-007** Isolasi cabang = global scope + policy + konteks aktor; UI bukan pagar. Pengecualian: master pelanggan & resep bersifat global (ADR-012).
- **ADR-008** Penomoran dokumen per bulan per tipe, tidak didaur ulang, void tercatat.
- **ADR-009** WhatsApp di belakang antarmuka gateway yang dapat diganti penyedianya; async, tidak menggagalkan transaksi.
- **ADR-010** Dokumen dulu, kode kemudian: PRD → PRD Detail → Blueprint → persetujuan owner → Fase 0.
- **ADR-011** Multi-bahasa sejak Fase 0 (keputusan owner 2026-10-17): antarmuka dwibahasa Indonesia (bawaan) + Inggris. Semua teks UI lewat mekanisme lokalisasi Laravel (`lang/id`, `lang/en`, kunci `ui.*`); tidak ada string tampilan keras di Blade/controller/service. Preferensi bahasa per pengguna tersimpan di `users.locale`; tamu memakai sesi; locale di luar daftar dukungan (`config('app.supported_locales')`) ditolak. Modul baru WAJIB melengkapi kedua berkas bahasa untuk setiap kunci yang ditambahkannya.
- **ADR-012** Master pelanggan, resep & produk berlaku GLOBAL (keputusan owner 2026-10-17, sesudah gerbang MVP): pelanggan yang terdaftar di satu cabang dapat dilayani dan ditransaksikan di semua cabang, dan resepnya ikut berlaku global; produk memang sudah global sejak M.1. `customers.branch_id` = cabang pendaftaran dan `prescriptions.branch_id` = cabang pemeriksaan — keduanya atribut asal untuk atribusi, BUKAN pagar (model tidak memakai `BranchScoped`; pagarnya izin + penolakan aktor platform). Data transaksional tetap terpagar cabang penuh (ADR-007): penjualan, pembayaran, stok, PR/PO, penerimaan, transfer, jurnal, laporan. Cabang transaksi penjualan = cabang operasi aktor (atau `branch_id` eksplisit yang aktor berhak atasnya), tidak pernah cabang asal pelanggan — stok keluar dan pendapatan tercatat di cabang tempat transaksi terjadi.
- **ADR-013** Tata letak SIDEBAR menggantikan Varian A (keputusan owner 2026-10-18): sesudah menu aplikasi melampaui 17 tautan dan navigasi header harus dikelompokkan paksa pada perbaikan test view Fase 1, owner memutuskan navigasi berpindah ke sidebar kiri dengan klasifikasi menu & submenu (6 kelompok: Utama, Operasional, Pembelian & Persediaan, Akuntansi, Laporan, Master & Pengaturan), disetujui dari pratinjau `docs/preview/pratinjau-sidebar.html`. Kontrak yang TIDAK berubah: judul tepat satu kali (di topbar), pencarian global selalu di atas (di topbar), satu dropdown Aksi + satu Cetak per layar, detail = halaman penuh, dwibahasa. Varian A dan varian tanpa header (B) sama-sama tidak dipakai lagi.
- **Terbuka untuk owner:** (1) persetujuan Blueprint v2.0 ini; (2) payroll di F1 atau F4; (3) penyedia gateway WA (F3.4); (4) metode costing (gerbang F4.2); (5) kata "mulai Fase 0".

---
*Akhir Blueprint v2.0 — menggantikan v1.0. Perubahan berikutnya hanya lewat revisi ber-commit di repositori Lentera.*
