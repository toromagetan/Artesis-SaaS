# Matriks Test Keamanan — Isolasi Tenant

Dokumen ini memetakan persyaratan isolasi tenant di PRD Bagian 6 ke
kelas test yang **benar-benar ada dan menguji perilaku tersebut**
(D-02). Aturan pemeliharaan:

1. Baris hanya boleh berstatus **Ter-cover** bila kelas test yang
   dikutip ada di repo dan memuat test negatif antar-tenant untuk
   perilaku itu. Nama kelas yang "terdengar relevan" tidak cukup.
2. Modul baru yang menyentuh data tenant wajib menambah test negatif
   dua-tenant dan memperbarui peta ini pada PR yang sama.
3. Status persyaratan mengikuti PRD Bagian 6; dokumen ini mencatat
   bukti testnya, bukan mengubah status persyaratan.

## Peta PRD Bagian 6 → test

| Area (PRD Bagian 6) | Kelas test pen-cover (bukti) |
|---|---|
| Direct object IDOR | `Security/IdentitySecurityMatrixTest` (IDOR antar-cabang baca & tulis), `MasterTenantIsolationTest` (detail produk/pelanggan/resep tenant lain 404), `Identity/UserTenantIsolationTest` (membuka pengguna asing/platform ditolak), `LabTechnicianAssignmentIsolationTest` (jalur HTTP tidak menyeberang), `BranchPerimeterRemainderTest` |
| Query listing & detail | `MasterTenantIsolationTest`, `AccountingTenantIsolationTest`, `Platform/DashboardTenantMetadataTest`, `GuestScopeAndSettingsIsolationTest` (tamu HTTP tidak dapat membaca data tenant), `Security/GlobalSearchTenantIsolationTest` (D-02), `Security/CommsTenantIsolationTest` (D-02, daftar inbox) |
| Create/update/delete | `MasterTenantIsolationTest` (pembuatan menstempel tenant), `AccountingTenantIsolationTest` (posting gagal-tertutup; override tenant tervalidasi), `RoleSettingsOwnershipTest` (tidak dapat menyentuh peran tenant lain), `BranchPerimeterRemainderTest` (transfer ke cabang tenant lain ditolak), `MasterRemainderIsolationTest` + `AccountingTenantIsolationTest` (tenant berdata tidak dapat dihapus) |
| Branch switching | `BranchPerimeterRemainderTest`, `Security/IdentitySecurityMatrixTest` (butir C), `WarrantyWarehouseTenantIsolationTest` (partisi gudang mengikuti tenant kasus) |
| Customer & prescription | `MasterTenantIsolationTest::test_customers_and_prescriptions_do_not_cross_tenants`, `Membership/MemberPortalTest` (portal hanya data pemilik token), `Security/CommsTenantIsolationTest` (percakapan per pelanggan) |
| Product & product parent | `MasterTenantIsolationTest` (web + pembuatan + induk per tenant), `Security/ApiTenantIsolationTest` (D-02, API produk) |
| Sales & invoice | `Security/CrossTenantPrintMatrixTest` (D-02, seluruh keluarga cetak penjualan), `Security/ApiTenantIsolationTest` (D-02, API penjualan), `Security/ReportExportTenantIsolationTest` (D-02, agregat), `TenantExportIsolationTest` (baris penjualan pada ekspor penuh) |
| Inventory & stock transfer | `BranchPerimeterRemainderTest` (transfer antar-tenant ditolak, intra-tenant lolos), `WarrantyWarehouseTenantIsolationTest` (antrean stok rusak terpartisi), `Security/ApiTenantIsolationTest` (D-02, API stok) |
| Purchase order | `Security/CrossTenantPrintMatrixTest` (D-02, cetak PO/PR/penerimaan), `WarrantyWarehouseTenantIsolationTest` (PO pengganti garansi: izin + partisi supplier), `TenantExportIsolationTest` (baris anak rantai pembelian pada ekspor) |
| Supplier & master data | `MasterTenantIsolationTest::test_suppliers_and_insurances_do_not_cross_tenants`, `MasterRemainderIsolationTest` (dokter, level membership, baris master warisan NULL tidak terlihat tenant) |
| User & role | `Identity/UserTenantIsolationTest`, `RoleSettingsOwnershipTest` (peran templat tidak dapat diubah tenant; assignment tidak menyeberang) |
| Settings | `RoleSettingsOwnershipTest::test_settings_are_scoped_per_tenant`, `GuestScopeAndSettingsIsolationTest` (tulis setting menghormati ember; aktor tanpa tenant tidak dapat menulis setting global) |
| WhatsApp gateway & inbox | `WaWebhookTenantMappingTest` (nomor unik memetakan ke pelanggan pemilik; nomor bersama tidak diatribusikan; inbox terisolasi), `Security/CommsTenantIsolationTest` (D-02). Catatan PRD tetap berlaku: gateway masih satu per instalasi (OD-4 terbuka) |
| Audit log | **Parsial** sesuai PRD (log membawa `branch_id`; kolom `tenant_id` khusus belum ada). Bukti yang ada: `TenantExportIsolationTest::test_denials_are_audit_logged`, `Platform/AuditCoverageTest`, `Platform/AuditLogViewerTest` |
| Report & export | `Security/ReportExportTenantIsolationTest` (D-02, isi ekspor CSV), `AccountingTenantIsolationTest::test_financial_reports_only_aggregate_own_tenant`, `TenantExportIsolationTest` |
| Backup & restore | `Platform/BackupAccessControlTest` (tenant ditolak semua operasi backup; platform penuh; manipulasi path 404), `Platform/BackupHttpTest`, `TenantExportIsolationTest` (arsip terverifikasi terisolasi di disk). Restore per tenant diputuskan tidak dibangun (PRD) |
| File storage | **Parsial** sesuai PRD: arsip ekspor per direktori tenant terbukti di `TenantExportIsolationTest::test_created_archive_is_verified_and_isolated_on_disk`; berkas unggahan lain masih disk bersama per instalasi dan belum memiliki test negatif khusus |
| Cache | **Belum** sesuai PRD (tanpa awalan tenant; pengerasan Fase 6) — belum ada test; baris ini dinyatakan terbuka secara eksplisit |
| Queue & scheduler | `GuestScopeAndSettingsIsolationTest` (konteks sistem eksplisit untuk seeder & penanda pulih sesudah eksepsi), `AccountingSatelliteIsolationTest` (jurnal konsol penyusutan membawa tenant aset; posting payroll hanya tenant sendiri), `Concurrency/OfflineSyncRaceTest` |
| Tenant suspension | `TenantLifecycleEnforcementTest` (pengguna tenant tersuspensi ditolak; token API berhenti & pulih; tenant lain tidak terpengaruh) |
| Tenant deletion/offboarding | **Parsial** sesuai PRD: ekspor penuh per tenant (`TenantExportIsolationTest`) + pagar penghapusan tenant berdata (`MasterRemainderIsolationTest`, `AccountingTenantIsolationTest`); penghapusan terjadwal belum dibangun (OD-5) dan belum ada testnya |

## Permukaan khusus

| Permukaan | Bukti |
|---|---|
| Cetak (15 rute) | `Security/CrossTenantPrintMatrixTest` (D-02): 14 rute menolak aktor tenant lain dengan bersih — struk, orderan, invoice, cetak resep penjualan, retur, PO, PR, penerimaan, transfer, jurnal, resep klinik, profil & daftar pengguna, label. **Satu temuan terbuka: `payments.print`** (lihat di bawah) |
| Pencarian global `/cari` | `Security/GlobalSearchTenantIsolationTest` (D-02) |
| API v1 baca | `Security/ApiTenantIsolationTest` (D-02) + `MasterTenantIsolationTest::test_api_reads_are_tenant_fenced` |
| Gerbang paket & konteks | `PlanGateTenantContextTest` (aktor unassigned/dangling/platform ditolak fitur bisnis), `SaasEnforcementTest`, `TenantLifecycleEnforcementTest` |
| HTTP tamu & konteks sistem | `GuestScopeAndSettingsIsolationTest` — batas kepercayaan baca in-process diatur ADR-022 |
| Perangkat offline & sinkronisasi | `OfflineDeviceTenantIsolationTest` (snapshot hanya tenant pemilik perangkat), `OfflineSyncUuidClassificationTest`, `Concurrency/OfflineSyncRaceTest`, `BranchPerimeterRemainderTest` (registrasi & pencabutan perangkat berpagar perusahaan) |
| Webhook WhatsApp | `WaWebhookTenantMappingTest` (tanda tangan tidak sah ditolak; pemetaan nomor unik-atau-yatim) |
| Portal member | `Membership/MemberPortalTest` (token dicabut/tidak dikenal 404; penerbitan berpagar izin) |
| Matriks aktor umum | `Security/IdentitySecurityMatrixTest` (A aktor berizin lolos; B tanpa izin 403; C IDOR cabang; D platform ditolak aksi bisnis; E pengguna nonaktif ditolak; F izin dicabut berlaku permintaan berikutnya), `Security/PermissionTighteningTest` |

## Temuan terbuka

**`payments.print` — cetak pembayaran kolektif belum berpagar tenant**
(D-02, 2026-11-12). Aktor tenant lain yang membuka
`/pembelian/pembayaran/{id}/cetak` menerima HTTP 500, bukan
penolakan: model `PurchasePayment` tidak memakai scope sehingga
route-model binding meloloskan pembayaran tenant asing, controller
tidak memeriksa otorisasi, dan view cetak crash pada relasi berpagar
yang bernilai null. Test pembuktinya dipertahankan di
`Security/CrossTenantPrintMatrixTest::test_payments_print_denied_for_other_tenant`
dan ditandai *incomplete* secara eksplisit sampai modul perbaikan
payments selesai; aksi sibling `payments.result` berbentuk sama dan
wajib diperiksa pada modul yang sama. Perbaikan adalah pekerjaan
modul terpisah di luar D-02.
