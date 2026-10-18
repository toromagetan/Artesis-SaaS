# ROADMAP — LENTERA: Rencana Pembangunan Berfase & Bergerbang

| | |
|---|---|
| Dokumen | Roadmap pembangunan (dokumen kendali urutan) |
| Produk | **Lentera** — Sistem Manajemen & POS Toko Optik, kepemilikan penuh |
| Tanggal | 17 Oktober 2026 |
| Status | Draf untuk persetujuan owner |
| Relasi | PRD (`docs/PRD.md`) menentukan *apa* · PRD Detail (`docs/PRD-DETAIL.md`) menentukan *perilaku per menu* · Blueprint (`docs/BLUEPRINT.md`) menentukan *bagaimana dibangun* · **Roadmap ini menentukan *urutan, dependensi, dan gerbang* — tidak ada pekerjaan yang sah di luar urutan ini tanpa revisi roadmap ber-commit yang disetujui owner.** |

---

## 1. Prinsip Roadmap (mengikat)

1. **Urutan tidak dilompati.** Fase N+1 tidak dimulai sebelum gerbang keluar Fase N lulus dan owner memberi kata mulai. Di dalam fase, tugas dikerjakan mengikuti dependensinya.
2. **Per modul, bukan per berkas.** Satu modul diselesaikan sekaligus (skema + service + UI + test) sebelum modul berikutnya dimulai; jangan mencampur dua modul dalam satu pekerjaan/commit.
3. **Selesai = terverifikasi.** Definisi selesai global (Bagian 2) berlaku untuk setiap tugas; "kode jalan" bukan selesai.
4. **Setiap gerbang menghasilkan bukti.** Laporan gerbang mengikuti standar bukti mentah (Blueprint G.4): identitas commit, ID run CI + angka test/assertion, dan hasil verifikasi — bukan narasi.
5. **Berhenti itu sah.** Tugas yang menabrak keputusan yang belum diambil owner berhenti dengan status BLOCKED + menyebut keputusan yang dibutuhkan; tidak ditebak, tidak dilompati diam-diam.
6. **Perubahan roadmap = revisi dokumen.** Cakupan hanya berubah lewat revisi file ini ber-commit, disetujui owner. Tidak ada fitur selundupan.

## 2. Definisi Selesai (Definition of Done — berlaku global)

Sebuah tugas/modul dinyatakan selesai hanya bila SEMUA terpenuhi:
- [ ] Skema & migrasi sesuai kamus data Blueprint (atau deviasi tercatat sebagai revisi Blueprint).
- [ ] Service + validasi + policy terimplementasi sesuai spesifikasi perilaku modul (Blueprint Bagian D).
- [ ] UI sesuai PRD Detail untuk menu terkait + konvensi UI bersama (Blueprint F.2) — termasuk bahasa desain clean/modern/elegan (F.2.0), aturan detail halaman penuh (bukan model preview), dan model tombol terkelompok (tepat satu dropdown Aksi + satu dropdown Cetak per layar, tanpa spam tombol). Judul halaman tampil tepat satu kali (tidak ganda di header dan body) dan pencarian global selalu tersedia di bagian atas.
- [ ] Test perilaku untuk operasi tulis utama lulus.
- [ ] Semua teks UI lewat kunci lokalisasi dengan berkas `id` + `en` lengkap (ADR-011); tidak ada string tampilan keras.
- [ ] Matriks keamanan 6 kasus (Blueprint G.1.2) lulus untuk modul itu.
- [ ] Test rekonsiliasi lulus bila modul menyentuh uang/stok.
- [ ] **Uji regresi penuh + uji konkurensi lulus** (Blueprint G.1 butir 7) — wajib untuk setiap tugas yang menyentuh hitungan uang/stok dan di setiap gerbang fase; kegagalan satu skenario konkurensi = tugas belum selesai.
- [ ] CI penuh hijau pada run yang dikutip ID-nya.
- [ ] Commit bersih: hanya berkas modul itu; pesan commit menyebut nomor tugas roadmap.
- [ ] Laporan tugas disampaikan (hasil, cakupan, test, git, sisa risiko).

## 3. Peta Fase (ringkasan)

| Fase | Nama | Isi inti | Gerbang keluar (inti) | Milestone |
|---|---|---|---|---|
| 0 | Fondasi | Scaffold, CI, Identity, pagar cabang, audit, pola UI | Matriks keamanan Identity lulus; CI wajib hijau | **MS-0 Fondasi Terkunci** |
| MVP | Inti Operasional | Katalog → Resep → Penjualan → Lab dasar → Pembelian → Stok → Jurnal dasar → Laporan → Dashboard | Siklus toko penuh tanpa selisih; smoke test owner | **MS-1 Toko Berjalan** |
| 1 | Akuntansi Penuh | CoA Coretax, buku besar, laporan keuangan, aset, tutup buku, (payroll*) | Periode contoh rekonsiliasi 100%; periode terkunci menolak posting | **MS-2 Keuangan Utuh** |
| 2 | QC & Lab | QC 13 titik, PR otomatis dari penjualan, status proses turunan | Complete mustahil tanpa QC lulus; edging mustahil sebelum lensa diterima (test negatif) | **MS-3 Lab Terlacak** |
| 3 | Penjualan+, WA, Member | DP/split/cicilan, komisi dokter, gateway WA, membership & poin, portal | Skenario cicilan rekonsiliasi; log WA 100%; poin seimbang | **MS-4 Pelanggan Terhubung** |
| 4 | Inventaris+, Analitik, API | Opname, gerbang costing/FIFO, expiry, label, analitik, API | Opname terekonsiliasi; keputusan costing ter-ADR | **MS-5 Gudang Cerdas** |
| 5 | Riset | Spike AI asisten, fitting AI, offline kasir | Laporan kelayakan per butir | **MS-6 Peta Masa Depan** |

\* Payroll: keputusan owner — Fase 1 atau Fase 4 (lihat Bagian 10).

## 4. Dependensi Antar Fase (gambaran)

```
Fase 0 ──► MVP ──► Fase 1 ──┬─► Fase 2
                            ├─► Fase 3 (jurnal piutang/komisi/poin butuh F1)
                            └─► Fase 4 (analitik butuh laporan keuangan F1)
Fase 2, 3, 4 ──► Fase 5 (riset di atas sistem yang utuh)
```
Fase 2 dapat berjalan sejajar sesudah Fase 1 bila sumber daya memungkinkan, tetapi rilisnya tetap berurutan dan masing-masing bergerbang sendiri.

## 5. FASE 0 — FONDASI

**Tujuan:** kerangka yang membuat semua modul berikutnya aman dibangun: identitas, pagar cabang, pola uji, dan pipeline.

| Tugas | Hasil yang diserahkan | Dependensi |
|---|---|---|
| F0.1 Scaffold & CI | Repo berisi aplikasi Laravel 13 terstruktur `app/Modules/*`; workflow CI 5 job (Blueprint G.2) berjalan dan hijau pada commit kerangka; aturan branch: PR ke `main`, tanpa push langsung | — |
| F0.2 Identity & Master dasar | Migrasi + seeder Bagian B.1 & registry izin Bagian C; UI: login, daftar pengguna, peran & matriks izin, cabang; aktor `is_platform` terpisah dari pelaku bisnis | F0.1 |
| F0.3 Pagar cabang | `BranchContext`, global scope cabang, pola Policy per sumber daya, helper `managedBranchIds()` | F0.2 |
| F0.4 Perkakas lintas | Helper `Money` (integer-sen), `AuditLogger`, pemindai pola terlarang (bc*, float-uang, izin di luar registry) sebagai job CI + `ArchitectureTest` | F0.1 |
| F0.5 Pola UI & dokumen | Token desain + komponen bersama bahasa **clean/modern/elegan** (Blueprint F.2.0) terimplementasi; layout bersama (header, filter bar, tabel sort, paginasi); pola **halaman detail penuh** (bukan preview/modal/drawer) dibuktikan pada satu modul contoh ujung-ke-ujung; mesin cetak dasar + satu dokumen contoh (A5) | F0.2 |
| F0.6 Lingkungan uji | Instalasi uji (staging lokal/server uji) dengan checklist paritas lingkungan (Blueprint G.3) terdokumentasi | F0.1 |

**Gerbang keluar Fase 0 (semua wajib):**
- [ ] CI hijau penuh pada run yang dikutip.
- [ ] Matriks keamanan 6 kasus lulus pada modul Identity (termasuk aktor platform ditolak dari aksi bisnis & IDOR antar cabang).
- [ ] Pemindai pola terlarang terbukti bekerja (commit uji berisi `bcadd(` ditolak CI — lalu dicabut).
- [ ] Owner menyetujui pola UI pada modul contoh — termasuk tampilan clean/modern/elegan dan pola halaman detail penuh (bukan model preview) — dengan tata letak **Varian A (header ramping)** yang sudah diputuskan owner 2026-10-17 dari pratinjau perbandingan (judul tampil satu kali di header; pencarian global selalu di header).
- [ ] Laporan gerbang memenuhi standar bukti Blueprint G.4.
**Risiko & mitigasi:** godaan memulai fitur sebelum fondasi selesai → gerbang ini eksplisit; tidak ada pengecualian "sekalian".

## 6. MVP — INTI OPERASIONAL

**Tujuan:** Lentera dapat menjalankan satu toko optik sungguhan dari ujung ke ujung (milestone **MS-1 Toko Berjalan**).

| Tugas | Hasil yang diserahkan | Dependensi |
|---|---|---|
| M.1 Catalog | Skema B.2 + service + UI lima tipe produk (frame/lensa/RX/softlens/aksesori) + atribut per tipe | F0 |
| M.2 CRM & Clinical | Pelanggan + resep grid OD/OS terkunci-sesudah-terpakai + `PrecalService` 11 parameter + test pemindaian PDR/PDL pada output & cetakan | F0 |
| M.3 Sales inti | Buat transaksi (snapshot resep & harga), pembayaran append-only (multi-metode, tanpa split/DP dulu), dokumen Orderan/Resep/Invoice dengan satu tombol cetak per konteks, void ber-alasan dengan pembalikan penuh | M.1, M.2 |
| M.4 Inventory inti | `stock_movements` + `InventoryService` single-writer + stok per cabang + integrasi stok keluar di penjualan (frame/aksesori anti-negatif; lensa boleh backorder berbendera) | M.1; integrasi → M.3 |
| M.5 Lab dasar | `lens_processes`, antrean dengan hari berjalan & sorotan lewat estimasi, assign teknisi, `complete()` (durasi membeku; penyelesaian kedua ditolak) | M.3 |
| M.6 Purchasing ujung ke ujung | PR (submit/approve/convert + gabung se-supplier) → PO → penerimaan bertahap → pembayaran tunggal → **pembayaran kolektif FIFO** dengan kontrak anti-tercatat-sebagian + test injeksi kegagalan + retur pembelian | M.1, M.4 |
| M.7 Transfer distribusi | Kirim per item dari detail PO (sisa terhitung), dokumen transfer, penerimaan di cabang tujuan, stok dalam perjalanan terlihat di laporan | M.6 |
| M.8 Impor stok awal | Template 2 sheet (ID/Nama cabang), preview validasi penuh (nol impor sebagian senyap), commit satu transaksi, policy eksplisit peran tertinggi | M.4 |
| M.9 Jurnal otomatis dasar | Listener posting untuk Sale/Receipt/Payment/Returns (template Blueprint E.1) + jurnal balik untuk void + daftar jurnal baca-saja | M.3, M.6 |
| M.10 Laporan inti | Penjualan, Pembelian (bruto + kolom Retur + neto), Stok, Ringkasan Utang Supplier — ekspor Excel setara angka layar (diuji) | M.9 |
| M.11 Dashboard operasional | Kartu & daftar per peran sesuai PRD Detail Bagian 1 dengan pagar cabang | M.3, M.5, M.6 |

**Gerbang keluar MVP (semua wajib):**
- [ ] Seluruh test Blueprint G.1 hijau, termasuk injeksi kegagalan pembayaran kolektif (nol sisa baris) dan pemindaian PDR/PDL.
- [ ] Siklus penuh di instalasi uji: (1) jual frame+lensa dengan resep → proses → Complete; (2) PR→PO→terima bertahap→bayar kolektif 3 receipt FIFO; (3) transfer dari PO→terima di cabang lain; (4) impor stok awal satu cabang — semua tanpa selisih stok/jurnal (dibuktikan laporan rekonsiliasi).
- [ ] Smoke test owner di instalasi uji untuk 4 siklus di atas.
- [ ] Angka CI dikutip dengan ID run; laporan gerbang standar G.4.
**Risiko & mitigasi:** pembayaran kolektif adalah titik paling rawan (riwayat bug produksi di proyek pendahulu) → kontrak D.7 + injeksi kegagalan adalah gerbang, bukan saran. Resep/combobox: pilihan pelanggan di formulir resep wajib ter-registrasi sebelum simpan (kasus tepi D.5 diuji eksplisit).

## 7. FASE 1 — AKUNTANSI PENUH

**Tujuan:** dari jurnal transaksi menjadi keuangan yang dapat ditutup per periode (milestone **MS-2 Keuangan Utuh**).

| Tugas | Hasil | Dependensi |
|---|---|---|
| F1.1 CoA & bagan akun | `accounts` + seeder bagan selaras Coretax + UI kelola akun (nonaktif, bukan hapus) | MVP |
| F1.2 Buku besar | Ledger per akun/periode dengan penelusuran ke jurnal sumber | F1.1 |
| F1.3 Laporan keuangan | Neraca; Laba Rugi total & per cabang (dimensi `branch_id` di baris jurnal); Arus Kas sederhana; ekspor | F1.2 |
| F1.4 Aset tetap | Master aset + job penyusutan bulanan terjurnal | F1.1 |
| F1.5 Tutup buku | `period_locks` ditegakkan di PostingService untuk SEMUA modul; UI lock/unlock (unlock: Owner + alasan + audit) | F1.3 |
| F1.6 Dashboard keuangan | Piutang, utang, LR ringkas per cabang di Dashboard Owner | F1.3 |
| F1.7 Payroll & komisi* | Payroll per periode terjurnal; komisi dokter & teknisi dari data final (void mengoreksi) | F1.1 |

**Gerbang keluar Fase 1:**
- [ ] Periode contoh (data uji sebulan penuh) rekonsiliasi 100%: total laporan = total jurnal; neraca seimbang.
- [ ] Posting bertanggal periode terkunci ditolak dari minimal tiga modul berbeda (Penjualan, Pembelian, Jurnal Manual) — dibuktikan test.
- [ ] Uji paralel: laporan manual (spreadsheet dari data yang sama) cocok dengan laporan sistem untuk periode contoh.
**Risiko:** kesalahan template posting bersifat sistemik → setiap template E.1 memiliki test debit/kredit sendiri sebelum F1.3 dimulai.

## 8. FASE 2 — QC FASET & PROSES LAB

**Tujuan:** proses lensa terlacak titik-demi-titik dengan akuntabilitas teknisi (milestone **MS-3 Lab Terlacak**).

| Tugas | Hasil | Dependensi |
|---|---|---|
| F2.1 Template QC | Seeder 13 titik bawaan + UI kelola titik (wajib/tidak, urutan, aktif) | MVP |
| F2.2 Perekaman QC | `qc_checks` per percobaan (riwayat tidak ditimpa), catatan wajib saat gagal, syarat Complete menegakkan semua titik wajib lulus | F2.1 |
| F2.3 Koreksi paritas Artesis (keputusan owner 2026-10-18) | Order lab sebagai dokumen DICABUT; pengganti: PR otomatis dari penjualan (`purchase_requests.sale_id`; RX selalu, stok hanya kekurangan) + status proses turunan PR→PO→terima + edging tergerbang penerimaan lensa | F2.2, M.6 |

**Gerbang keluar Fase 2:**
- [ ] Test negatif: Complete ditolak selama ada titik QC wajib yang belum lulus/gagal — untuk semua jalur (UI & service).
- [ ] Orderan uji ujung-ke-ujung mengikuti rantai Artesis (jual RX → PR otomatis → PO → terima → edging → QC gagal 1 titik → perbaiki → lulus → Complete) tercatat lengkap dengan teknisi & waktu; edging sebelum terima terbukti ditolak.

## 9. FASE 3 — PENJUALAN DIPERLUAS, WHATSAPP, MEMBERSHIP

**Tujuan:** penjualan fleksibel seperti praktik toko nyata, pelanggan terhubung lewat WA, dan loyalitas tercatat akuntansi (milestone **MS-4 Pelanggan Terhubung**).

| Tugas | Hasil | Dependensi |
|---|---|---|
| F3.1 DP, split, PPN per transaksi | DP menjadi piutang terjadwal; split multi-metode dalam satu transaksi; mode PPN inklusif/eksklusif per transaksi | MVP |
| F3.2 Cicilan & piutang | `sale_installments`, daftar umur piutang (lancar/>30/>60/>90), penerimaan cicilan terjurnal | F3.1, F1 |
| F3.3 Dokter perujuk & komisi | Dokter di transaksi; komisi terakru per periode; koreksi otomatis saat void | F3.1, F1.7 |
| F3.4 Gateway WA | Antarmuka `MessagingGateway`, implementasi **Meta Cloud API langsung** (keputusan owner 2026-10-18, sementara — dapat diganti lewat antarmuka), kredensial & konfigurasi diatur via UI Pengaturan, antrean + coba-lagi 3×, log kirim lengkap | F3.1 |
| F3.5 Otomasi pesan | Invoice via WA dari transaksi; reminder pengambilan & after-service terjadwal; ucapan ulang tahun; kegagalan gateway tidak menggagalkan transaksi | F3.4 |
| F3.6 Membership & poin | Level member; poin earn/redeem/expire dengan jurnal liabilitas; daftar follow-up after-service | F3.1, F1 |
| F3.7 Portal member | Halaman publik ber-token (hash disimpan, dapat dicabut): poin, riwayat beli, status orderan | F3.6, F3.4 |
| F3.8 Inbox & kampanye | Inbox dua arah tertempel ke pelanggan; kampanye bersegmen dengan persetujuan Owner wajib + opt-out dihormati | F3.4 |

**Gerbang keluar Fase 3:**
- [ ] Skenario gabungan (DP + split 2 metode + 3 cicilan sampai lunas) rekonsiliasi penuh: transaksi = pembayaran = piutang = jurnal = laporan.
- [ ] 100% pesan keluar tercatat di log dengan status; uji kegagalan gateway: transaksi tetap tersimpan, pesan masuk antrean ulang.
- [ ] Saldo liabilitas poin di jurnal = Σ poin aktif di `points_ledger`.

## 10. FASE 4 — INVENTARIS LANJUT, ANALITIK, API

**Tujuan:** gudang disiplin dan keputusan berbasis data (milestone **MS-5 Gudang Cerdas**).

| Tugas | Hasil | Dependensi |
|---|---|---|
| F4.1 Opname terjadwal | Jadwal → lembar hitung (snapshot) → review → approval menerbitkan gerakan selisih; kunci mutasi cabang selama counting | MVP |
| F4.2 **Gerbang costing** | Laporan audit metode berjalan vs FIFO (dampak ke nilai persediaan & HPP) → **keputusan owner tertulis** → ADR baru → implementasi metode terpilih + migrasi nilai. **DIPUTUSKAN 2026-10-18: FIFO per cabang (ADR-014 di Blueprint)** — implementasi menunggu kata memulai Fase 4 | F1 |
| F4.3 Expiry softlens | Peringatan < 90 hari di daftar & dashboard; penjualan softlens kedaluwarsa diblokir | MVP |
| F4.4 Label barcode | Cetak massal EAN-13/Code 128 per produk/batch penerimaan | MVP |
| F4.5 Analitik | Supplier Analytics (lead time, retur) & Sales Analytics (terlaris, margin, tren cabang) | F1 |
| F4.6 API read-only | Token bercakupan baca, pencatatan pemakaian, pencabutan seketika | F0 |
| F4.7 Payroll* | *(bila owner menunda dari Fase 1)* payroll + laporan gaji | F1 |

**Gerbang keluar Fase 4:**
- [ ] Satu siklus opname penuh terekonsiliasi ke jurnal persediaan.
- [ ] Keputusan costing terdokumentasi sebagai ADR dan angka persediaan pasca-migrasi cocok dengan laporan auditnya.
- [ ] Angka analitik = angka laporan sumber pada dua periode uji.

## 11. FASE 5 — RISET (bukan komitmen bangun)

Setiap butir berjalan sebagai **spike terbatas**: pertanyaan jelas → prototipe/uji terbatas → laporan kelayakan (nilai bisnis, biaya, risiko privasi, kompleksitas) → keputusan owner terpisah (bangun / tunda / coret). Tidak ada kode produksi dari fase ini.

| Spike | Pertanyaan yang dijawab |
|---|---|
| F5.1 Asisten AI bisnis | Apakah tanya-jawab read-only atas data toko (owner-only, dasar data selalu ditampilkan, kredensial AI milik pemilik) cukup akurat & aman pagar cabangnya untuk dibangun? |
| F5.2 Fitting AI | Apakah pengukuran PD/dimensi wajah dari kamera toko cukup andal (toleransi terukur vs alat ukur manual) tanpa menyimpan foto? |
| F5.3 Offline kasir | Apakah antrean transaksi lokal + sinkronisasi (konflik stok, nomor struk per perangkat, pembayaran) dapat dibuat aman untuk internet mati berjam-jam? |

**Gerbang keluar Fase 5:** tiga laporan kelayakan diterima owner; keputusan per butir tercatat sebagai revisi roadmap/ADR.

**Keputusan owner 2026-10-18 (laporan di `docs/spikes/`):**
- F5.1 Asisten AI bisnis — **BANGUN** (keputusan owner 2026-10-18 17:04): kredensial model AI (endpoint + API key + nama model) diatur owner lewat UI Pengaturan, terenkripsi, pola Gateway WA.
- F5.2 Fitting AI — awalnya **TUNDA + validasi fisik**, lalu **diubah owner 2026-10-18 17:04 menjadi BANGUN sekarang**. Pagar keamanan laporan tetap berlaku di dalam produk: angka kamera bersifat pendamping (rujukan pupilometer tetap ditampilkan), foto tidak pernah disimpan, hanya angka yang disimpan. Protokol validasi (`docs/spikes/F5.2-protokol-validasi.md`) tetap tersedia untuk dijalankan kapan pun.
- F5.3 Offline kasir — **BANGUN.** Cakupan v1 sesuai laporan: fondasi server (client_uuid idempoten, registri perangkat bertoken, endpoint sinkronisasi, antrean pengecualian + UI resolusi) lalu klien kasir PWA offline-first (snapshot katalog, antrean lokal, tunai-saja offline, satu perangkat per cabang). **SELESAI 2026-10-18** (fondasi + klien; gerbang 302 test/1799 asersi).

## 12. Kendali Perubahan & Keputusan Eksternal

- **Perubahan cakupan** hanya lewat revisi dokumen ini (atau PRD/Blueprint bila menyangkut perilaku/skema) yang di-commit dan disetujui owner; nomor tugas baru ditambahkan, tidak menyelundup ke tugas berjalan.
- **Keputusan owner yang menjadi dependensi:** (1) persetujuan roadmap ini + kata "mulai Fase 0"; (2) payroll di Fase 1 atau Fase 4 (memengaruhi urutan F1.7/F4.7); (3) ~~penyedia gateway WhatsApp resmi (gerbang F3.4)~~ DIPUTUSKAN 2026-10-18: Meta Cloud API langsung (sementara); (4) ~~metode costing (gerbang F4.2)~~ DIPUTUSKAN 2026-10-18: FIFO per cabang (ADR-014); (5) penyediaan instalasi uji & — kelak — server produksi per instalasi bisnis (checklist paritas Blueprint G.3).
- **Pelaporan berjalan:** setiap tugas selesai dilaporkan dengan format tetap (hasil, cakupan berkas, test + ID run CI, commit, sisa risiko); setiap gerbang fase dilaporkan sebagai satu laporan gerbang tersendiri.
- **Yang tidak dijanjikan roadmap ini:** tanggal kalender. Urutan dan gerbang bersifat mengikat; kecepatan mengikuti lulusnya gerbang, bukan tenggat buatan.

---
*Akhir Roadmap v1.0 — dokumen kendali urutan pembangunan Lentera. Revisi hanya ber-commit dengan persetujuan owner.*
