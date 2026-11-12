# ROADMAP — ARTESIS SaaS: Rencana Berbasis Dependensi & Risiko

| | |
|---|---|
| Dokumen | Roadmap SaaS (dokumen kendali urutan) — menggantikan Roadmap pembangunan pra-SaaS (kini arsip di Blueprint Bagian H) |
| Produk | **Artesis** — SaaS POS Optik & Operational Management (multi-tenant) |
| Baseline | `main` @ `43db5b54` (2026-11-12, penyelarasan D-01), repositori `toromagetan/Artesis-SaaS` |
| Relasi | PRD (`docs/PRD.md`) menentukan *apa & statusnya* · Blueprint (`docs/BLUEPRINT.md`, terutama Bagian J) menentukan *arsitektur aktual & target* · **Roadmap ini menentukan *urutan, dependensi, dan gerbang keluar*** |
| Aturan status | Status hanya berubah dengan bukti (test/CI/commit yang dikutip). **Tidak ada fase yang dinyatakan Completed tanpa bukti.** |

## 0. Keadaan awal (yang sudah terbangun sebelum roadmap ini)

- **Fitur bisnis optik lengkap** (Fase 0–5 pembangunan pra-SaaS, selesai 2026-10-18 + penutupan celah s.d. 2026-10-23) — arsip & buktinya di Blueprint Bagian H.
- **Lapisan platform SaaS** (Fase 1 SaaS, 2026-10-29): rebrand Artesis, 4 bahasa, model Plan/Subscription/override, penegakan server `PlanGate`, pendaftaran mandiri + penagihan transfer manual, paket go-live (runbook) — ter-merge s.d. `855a0c16`, suite 475 test hijau, CI 5/5.
- **Isolasi pengelolaan pengguna per tenant** + provisioning tenant dari panel (2026-10-30, s.d. `a96f757`).
- **Pembaruan 2026-11-12 (Fase 0 master-roadmap):** isolasi data bisnis antar-tenant dalam satu instansi **sudah terbangun** (PR #23–#33, merge s.d. `c4af9c87`), penegakan suspensi **sudah ada** (S-01), partisi gudang garansi per tenant (B-02, PR #34), dan ekspor data per tenant **sudah ada** (B-03, PR #35, merge `43db5b54`). Restore per tenant diputuskan tidak dibangun. Status tiap fase di bawah diperbarui pada D-01 sesuai bukti merge tersebut.

**Keputusan yang menggerbang roadmap ini:** OD-1 (topologi produksi) masih terbuka; **OD-2 (nasib ADR-012) DIPUTUSKAN 10 Nov 2026: isolasi per tenant penuh** (ADR-012 berlaku di dalam tenant). Fase 0–1 berjalan pada kedua topologi; Fase 2–3 wajib hanya untuk topologi satu instansi bersama, dan tetap bernilai sebagai pertahanan berlapis pada topologi instalasi-per-tenant.

## 1. Prinsip (mengikat, diwarisi + diperluas)

1. **Urutan berbasis dependensi** — fase dimulai bila dependensinya lulus gerbang keluar; tidak ada lompatan diam-diam.
2. **Per modul, bukan per berkas** — satu modul selesai (skema + service + UI + test) sebelum modul berikutnya; commit terpisah per modul lewat PR + CI hijau (branch protection `main`).
3. **Definisi selesai global** — untuk pekerjaan uang/stok: regresi penuh + konkurensi 6 skenario di MySQL nyata; untuk pekerjaan isolasi: test negatif antar-tenant pada area yang disentuh; untuk semua: standar bukti mentah Blueprint G.4.
4. **Dokumentasi mengikuti kode** — setiap fase yang mengubah perilaku memperbarui PRD/Blueprint pada PR yang sama atau PR dokumen yang menyertainya.
5. **Berhenti itu sah** — menabrak keputusan pemilik proyek (OD) = status Blocked + menyebut keputusannya.

## 2. Peta dependensi

```
Fase 0 (Dokumen) ──► Fase 1 (Fondasi Isolasi) ──► Fase 2 (Data Bisnis) ──► Fase 3 (Identitas & Konfigurasi)
                                                              │
Fase 4 (Siklus Hidup SaaS) ── (fondasi sudah ada; berjalan sejajar sesudah Fase 1 untuk suspensi)
Fase 1 ──► Fase 5 (Backup/Ekspor/Recovery) ──► Fase 6 (Kesiapan Produksi: menggerbang SEMUA fase)
```

---

## Fase 0 — Documentation & Architecture Alignment

- **Objective:** seluruh dokumen kendali menggambarkan produk yang sama (SaaS multi-tenant) dan sesuai bukti kode, agar agen/manusia tidak salah arah.
- **Scope:** README, PRD, Blueprint, Roadmap (dokumen ini); konsistensi ADR (status ADR-002/007/011/012 + ADR-020/021 baru); inventaris dokumen yang belum selaras (PRD-DETAIL, POLA-POLICY, AGENTS/CLAUDE) sebagai temuan — pembaruannya pekerjaan terpisah berpersetujuan.
- **Dependencies:** tidak ada (baseline kode `a96f757`).
- **Deliverables:** 4 dokumen terselaraskan; daftar keputusan terbuka (PRD Bagian 9); klasifikasi data 90 tabel (Blueprint J.3).
- **Acceptance criteria:** tidak ada pernyataan non-goal SaaS yang tersisa di 4 dokumen; setiap klaim status fitur menunjuk bukti kode/test; istilah konsisten (Platform, Tenant, Tenant Owner, Platform Superadmin, Branch, Tenant Isolation, Subscription, Plan, Billing); tautan internal valid.
- **Test requirements:** tidak ada perubahan kode — verifikasi = `git diff` hanya 4 berkas + pemeriksaan tautan/istilah.
- **Risks:** dokumen menyelaraskan diri ke arah yang belum diputuskan (topologi) → mitigasi: arah yang belum diputuskan ditulis sebagai OPEN DECISION, bukan ketetapan.
- **Exit criteria:** 4 dokumen di-review & disetujui pemilik proyek dan ter-commit.
- **Status: Implementasi dokumentasi selesai (D-01, 2026-11-12) — menunggu audit independen dan persetujuan merge pemilik proyek; status Selesai formal berlaku sesudah merge PR D-01.** Isi implementasi: paket penyelarasan 30 Okt diadopsi atas persetujuan pemilik proyek, disapu kemutakhirannya terhadap hasil Fase 0, dan diperluas: PRD-DETAIL (framing), POLA-POLICY (kontrak tenant + cabang), DEPLOY-RUNBOOK (URL repo + penanda OD-1), PARITAS-LINGKUNGAN, anotasi arsip, serta ADR-022 sebagai dokumen mandiri di `docs/adr/`.

## Fase 1 — Tenant Isolation Foundation

- **Objective:** konteks tenant yang tervalidasi menjadi fondasi semua query bisnis, dan risiko kritis yang tidak menunggu fase lain ditutup.
- **Scope:**
  1. `TenantContext` (resolusi tenant dari aktor di server; fail-closed tanpa konteks pada mode SaaS) — desain Blueprint J.2.1.
  2. Audit kepemilikan tenant seluruh model/tabel (memakai klasifikasi J.3) + migrasi `tenant_id` pada tabel milik tenant yang belum punya.
  3. `TenantScope` + pola bypass platform teraudit (J.2.2); validasi cabang ∈ tenant aktor (J.2.3).
  4. **Gerbang darurat backup:** rute backup tingkat instalasi hanya untuk aktor platform / konteks instalasi individual (menutup risiko kritis J.3 sebelum Fase 5).
  5. Konteks tenant pada job antrean & scheduler (J.2.6); awalan tenant pada cache & storage (J.2.4–5).
  6. Penanganan akun warisan tanpa tenant (tenantless): inventaris + aturan eksplisit (aktor tanpa tenant = instalasi individual; pada instansi SaaS bersama, akun tenantless non-platform tidak boleh ada — migrasi/penolakan terdokumentasi).
- **Dependencies:** Fase 0 lulus. Keputusan OD-1/OD-2 memengaruhi urgensi, bukan bentuk fondasi.
- **Deliverables:** service + scope + migrasi + test fondasi; Blueprint J.1 diperbarui ke keadaan baru.
- **Acceptance criteria:** AC-ISO-03 lulus (backup tertutup untuk tenant di mode bersama); request tanpa konteks tenant pada mode SaaS gagal tertutup pada jalur uji; bypass platform tercatat di audit log; tidak ada regresi suite penuh.
- **Test requirements:** regresi penuh + konkurensi; test unit TenantContext (resolusi, fail-closed, bypass); test HTTP gerbang backup (tenant ditolak, platform lolos, individual lolos).
- **Risks:** scope global yang keliru mengunci pengguna sah (lockout massal) → mitigasi: mode peringatan (log-only) satu rilis sebelum penegakan penuh; migrasi `tenant_id` pada tabel besar → jendela pemeliharaan + indeks lebih dulu.
- **Exit criteria:** semua acceptance lulus dengan bukti CI; mode penegakan penuh aktif; laporan gerbang standar G.4.
- **Status: Selesai (Fase 0 master-roadmap, PR #23–#33, merge s.d. `c4af9c87`)** — keenam butir scope terpenuhi: `TenantContext` lima mode + `TenantScope`; audit kepemilikan & stempel tenant tabel bisnis; validasi cabang ∈ tenant; gerbang backup platform (butir 4); konteks sistem eksplisit pada perintah terjadwal & pagar ember pada job; aturan akun tanpa tenant (mode standalone/unassigned/guest) terdokumentasi di POLA-POLICY + ADR-022. Bukti: suite 629 test hijau di head PR #33, CI 5/5 per PR.

## Fase 2 — Business Data Isolation

- **Objective:** data bisnis satu tenant tidak dapat dibaca/diubah/diekspor tenant lain dalam satu instansi.
- **Scope (per kelompok, masing-masing satu modul):** Customer · Prescription · Product & ProductParent · Supplier, Insurance, Doctor & master lain · Sales & invoice · Inventory (gerakan, lapisan FIFO, transfer, opname) · Purchasing (PR/PO/receipt/pembayaran) · Reports & exports. Termasuk penyesuaian ADR-012 sesuai keputusan OD-2 (master menjadi per tenant pada mode SaaS) dan `document_counters` per tenant.
- **Dependencies:** Fase 1 lulus (TenantContext + TenantScope + pola bypass). OD-2 diputuskan.
- **Deliverables:** pagar tenant per kelompok + migrasi pengisian `tenant_id` dari kepemilikan cabang + test negatif antar-tenant per kelompok.
- **Acceptance criteria:** AC-ISO-01 lulus untuk **setiap** kelompok pada scope (URL langsung 403/404; daftar bersih; ekspor bersih; cetakan bersih); transaksi sah intra-tenant tidak berubah perilaku (regresi penuh hijau); penempelan transaksi ke master tenant lain ditolak di service.
- **Test requirements:** per kelompok: test positif intra-tenant + negatif antar-tenant (baca, tulis, ekspor); regresi penuh + konkurensi di gerbang fase.
- **Risks:** master yang selama ini global dipakai bersama oleh data historis campuran (instalasi individual lama) → migrasi pengisian harus memetakan kepemilikan dari cabang asal & menyediakan laporan pengecualian; risiko performa scope pada laporan berat → indeks `(tenant_id, …)` per tabel panas.
- **Exit criteria:** seluruh kelompok lulus AC-ISO-01 dengan ID run CI dikutip; ADR-012 direvisi statusnya sesuai hasil.
- **Status: Selesai (Fase 0 master-roadmap)** — pagar tenant per kelompok ter-merge: master (S-02, PR #28), pagar cabang/tenant transaksi (PR #29), dokter & membership (PR #30), akuntansi inti (PR #31) & satelitnya (PR #32), penutupan temuan lintas modul (PR #33), partisi gudang garansi (B-02, PR #34). ADR-012 disesuaikan OD-2 (global di dalam tenant). **Satu pengecualian yang dicatat jujur:** `document_counters` tetap per instalasi (nomor dokumen global; kandidat modul tersendiri, bukan penahan fase ini).

## Fase 3 — Identity & Configuration Isolation

- **Objective:** identitas & konfigurasi menjadi milik tenant: peran, pengaturan, gateway komunikasi, dan jejak audit.
- **Scope:** role & permission per tenant (klon templat saat provisioning; matriks bawaan tidak lagi dibagi) · settings per tenant · WhatsApp gateway/template/inbox/kampanye per tenant (mengikuti OD-4) · kolom `tenant_id` audit log + pembacaan terpagar · token API & perangkat offline terikat tenant secara eksplisit.
- **Dependencies:** Fase 1 lulus; Fase 2 dianjurkan selesai lebih dulu (pola pagar yang sama). OD-4 diputuskan untuk butir WhatsApp.
- **Deliverables:** migrasi + kloning templat peran pada provisioning (termasuk tenant yang sudah ada) + pagar konfigurasi + test negatif.
- **Acceptance criteria:** Tenant Owner A yang mengubah peran/pengaturan/gateway tidak mengubah apa pun yang terlihat/berlaku di tenant B (diuji); audit log tenant hanya memuat baris tenant itu; provisioning tenant baru menghasilkan peran & pengaturan bawaan yang identik dengan templat.
- **Test requirements:** test negatif konfigurasi antar-tenant; test provisioning; regresi penuh.
- **Risks:** tenant lama yang terlanjur berbagi baris peran — perubahan perilaku sesudah kloning → mitigasi: kloning menyalin keadaan efektif saat migrasi, bukan templat bawaan.
- **Exit criteria:** acceptance lulus; tidak ada lagi baris konfigurasi bersama yang dapat diubah tenant pada instansi bersama.
- **Status: Selesai sebagian besar (Fase 0 master-roadmap)** — isolasi pengelolaan pengguna (2026-10-30); role kustom per tenant + role bawaan templat + settings per tenant (S-03, PR #27); token API & perangkat offline terikat ember tenant (PR #33); pembacaan audit log terpagar cabang/konteks (kolom `tenant_id` khusus belum ada). **Sisa:** gateway/template/inbox/kampanye WhatsApp per tenant — menunggu keputusan **OD-4**.

## Fase 4 — SaaS Lifecycle & Operations

- **Objective:** siklus hidup tenant lengkap & aman dioperasikan: aktivasi, suspensi, langganan, kuota, onboarding, offboarding.
- **Scope:** penegakan `tenants.is_active` (tolak login + putus sesi; J.2.9) · Subscription & PlanGate (sudah ada — pengerasan: penumpukan sisa masa saat konfirmasi, penagihan akun tambahan Team) · kuota & batas (sudah ada — audit kelengkapan seluruh jalur pembuatan entitas) · onboarding (verifikasi email + CAPTCHA/anti-penyalahgunaan pendaftaran) · offboarding (aliran status: aktif → nonaktif → masa tenggang → arsip/hapus, bersama Fase 5 untuk ekspor).
- **Dependencies:** fondasi langganan sudah terbangun (Fase 1 SaaS 2026-10-29); butir suspensi membutuhkan Fase 1 (konteks) untuk instansi bersama; offboarding membutuhkan Fase 5.
- **Deliverables:** penegakan suspensi + test; pengerasan billing; gerbang anti-penyalahgunaan pendaftaran; prosedur offboarding terdokumentasi.
- **Acceptance criteria:** AC-ISO-02 lulus (tenant tersuspensi tertolak total, termasuk sesi berjalan); pendaftaran bot tertahan CAPTCHA/verifikasi tanpa mengubah alur sah (test); konfirmasi billing dengan sisa masa aktif menghasilkan `expires_at` sesuai aturan yang diputuskan (dicatat sebagai revisi PRD bila berubah).
- **Test requirements:** test suspensi (login, request berjalan, penjadwalan); test billing lanjutan; regresi penuh.
- **Risks:** suspensi yang keliru mengunci tenant membayar → mitigasi: suspensi selalu reversible dari panel + teraudit + pemberitahuan tercatat.
- **Exit criteria:** acceptance lulus; runbook operasi diperbarui (DEPLOY-RUNBOOK) untuk prosedur suspensi/offboarding.
- **Status: In Progress** — fondasi siklus hidup (Plan, Subscription, Billing, kuota, panel, pendaftaran) **sudah terbangun & teruji** pada Fase 1 SaaS 2026-10-29; **suspensi tenant sudah ditegakkan** (S-01: login & akses ditolak). Yang tersisa: anti-penyalahgunaan pendaftaran (menunggu pilihan anti-bot pemilik proyek), pengerasan billing lanjutan, dan offboarding (menunggu OD-5).

## Fase 5 — Backup, Export & Recovery

- **Objective:** pemulihan bencana tingkat platform dan kedaulatan data per tenant berjalan berdampingan tanpa saling membocorkan.
- **Scope:** platform-wide DR backup (mekanisme ada — formalisasi jadwal, offsite, uji restore berkala) · **tenant data export** (semua tabel milik tenant, terfilter kepemilikan, format terdokumentasi) · **tenant restore** dari ekspor sendiri dengan validasi kepemilikan & integritas · operational backup (otomatis 02:00 — sudah ada) · otorisasi & audit unduhan sesuai Blueprint J.5.
- **Dependencies:** Fase 1 (konteks & klasifikasi kepemilikan final) dan Fase 2 (kepemilikan data bisnis sudah eksplisit per tabel — ekspor yang benar mustahil sebelum ini).
- **Deliverables:** layanan ekspor per tenant + restore per tenant + test integritas (hitung baris per tabel, checksum) + prosedur DR tertulis.
- **Acceptance criteria:** ekspor tenant A yang diperiksa baris-per-tabel tidak memuat satu pun baris tenant B; restore ekspor A ke instansi uji menghasilkan tenant yang berfungsi (login Owner, saldo stok & jurnal cocok) tanpa mengubah tenant lain di instansi tujuan; Tenant Owner tetap tidak dapat menyentuh snapshot global (AC-ISO-03 tetap lulus).
- **Test requirements:** test ekspor/restore ujung-ke-ujung dua tenant; uji restore DR tahunan dinyatakan sebagai prosedur (bukan hanya test otomatis).
- **Risks:** ekspor yang tidak lengkap (tabel terlewat) memberi rasa aman palsu → mitigasi: daftar tabel ekspor diturunkan dari klasifikasi J.3 secara mekanis + test yang gagal bila tabel baru belum terklasifikasi.
- **Exit criteria:** acceptance lulus; prosedur DR + ekspor menjadi bagian runbook.
- **Status: Selesai untuk ekspor; restore per tenant diputuskan tidak dibangun** — backup tingkat instalasi berjalan dan tergerbang platform (`backups.manage` + BackupPolicy); **ekspor data per tenant Implemented (B-03, PR #35)** dengan registry klasifikasi tabel mekanis (89 tabel) + test kelengkapan + test negatif dua tenant. Restore per tenant: keputusan pemilik proyek 11 Nov 2026 untuk tidak membangunnya (restore tetap whole-DB platform). **Sisa:** formalisasi offsite & uji restore DR berkala (menunggu kredensial penyimpanan eksternal).

## Fase 6 — Security & Production Readiness

- **Objective:** pembuktian menyeluruh bahwa instansi bersama aman & layak produksi — atau pernyataan tertulis bahwa belum.
- **Scope:** cross-tenant negative test suite menyeluruh (semua baris PRD Bagian 6 sebagai test) · IDOR testing per rute detail/aksi · isolasi queue & scheduler di bawah beban campuran tenant · isolasi cache & storage · uji kinerja & kapasitas (berapa tenant kecil–menengah per instansi pada perangkat keras acuan — angka terukur, bukan perkiraan) · security review menyeluruh (termasuk rute backup, token, webhook) · verifikasi independen (penguji selain penulis kode fase) · production readiness assessment tertulis.
- **Dependencies:** Fase 1–5 lulus (atau subset yang dinyatakan cukup untuk topologi yang dipilih OD-1 — penilaian itu sendiri adalah deliverable fase ini).
- **Deliverables:** suite negatif antar-tenant permanen di CI; laporan kapasitas terukur; laporan penilaian kesiapan produksi dengan status PASS / PASS DENGAN SYARAT / BELUM LAYAK per topologi.
- **Acceptance criteria:** seluruh baris PRD Bagian 6 memiliki test yang dikutip; tidak ada temuan keamanan kritis/tinggi yang terbuka; angka kapasitas menyebut perangkat keras, profil beban, dan ambang yang diuji.
- **Test requirements:** seluruh suite (regresi + konkurensi + negatif antar-tenant) hijau pada run yang sama; uji beban terdokumentasi dapat diulang.
- **Risks:** tekanan jadwal mengubah penilaian menjadi formalitas → mitigasi: penilai independen + status tertulis yang ditandatangani pemilik proyek.
- **Exit criteria:** laporan kesiapan diterima pemilik proyek; keputusan produksi per topologi tercatat.
- **Status: Not Started.**

---

## 3. Yang tidak dijanjikan roadmap ini

- Tanggal kalender. Urutan & gerbang mengikat; kecepatan mengikuti lulusnya gerbang dan keputusan pemilik proyek (OD-1 s.d. OD-6 di PRD).
- Mode satu instansi bersama di produksi sebelum Fase 6 menyatakan layak untuk topologi itu.

*Akhir Roadmap SaaS v1.0 — revisi hanya ber-commit dengan persetujuan pemilik proyek.*
