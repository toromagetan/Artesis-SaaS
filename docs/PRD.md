# PRD — LENTERA: Sistem Toko Optik Kepemilikan Penuh

| | |
|---|---|
| Dokumen | Product Requirements Document (PRD) |
| Produk | **Lentera** — Sistem Manajemen & POS Toko Optik |
| Repositori | `lentera` (baru, mandiri — **terpisah penuh dari repositori Artesis**) |
| Versi | 1.0 — Disetujui owner & terbangun penuh (2026-10-18) |
| Tanggal | 17 Oktober 2026 |
| Disusun oleh | Crong (lead engineer) |
| Dasar | Domain & alur bisnis yang terbukti di Artesis produksi (istori.my.id, terverifikasi s.d. 15 Okt 2026) + eksplorasi publik Lensiro (17 Okt 2026) |

---

## 1. Nama & Identitas

**Lentera** — bahasa Indonesia untuk pelita/lampion: sumber cahaya yang menerangi. Namanya memuat tiga lapis makna optik: *lente* (akar kata lensa), cahaya (hal yang dikoreksi dan diukur oleh optik), dan penerang usaha (kejelasan data bagi pemilik bisnis). Tagline: **"Jernih Datanya, Terang Bisnisnya."**

Identitas visual: hijau emerald pekat + emas — palet brand Optik Istori. Logo (assets/logo.webp): lingkaran lensa berfaset geometris berwarna emas dengan cahaya menyala di pusatnya — lensa sekaligus lentera.

## 2. Latar Belakang & Keputusan Produk

Dua bukti menjadi dasar produk baru ini:

1. **Artesis** membuktikan domainnya di produksi nyata milik owner: penjualan + resep, rantai pembelian PR→PO→penerimaan→pembayaran (tunggal & kolektif) + jurnal, transfer distribusi antar cabang, impor stok awal, laporan — dengan pagar akses lintas cabang yang terverifikasi. Semua itu berjalan sebagai **milik sendiri**, tanpa langganan.
2. **Lensiro** (SaaS PT Lensiro Digital Solusi) membuktikan keluasan yang diminta pasar: akuntansi penuh, QC faset & order lab, WhatsApp Business, membership, AI — tetapi dengan model berlawanan: langganan per toko/cabang, data di cloud vendor, fitur dikunci per tier.

**Keputusan owner (17 Okt 2026):** bangun produk **baru** bernama Lentera di repositori **baru** — menggabungkan kedalaman domain Artesis dengan keluasan fitur Lensiro, dengan model condong penuh ke Artesis: kepemilikan. **Repositori dan produksi Artesis tidak disentuh oleh proyek ini** dan melanjutkan hidupnya sendiri.

Lentera adalah basis kode baru (clean implementation). Yang dibawa dari Artesis adalah *spesifikasi perilaku yang terbukti* — alur, aturan bisnis, dan pagar keamanannya — bukan penyalinan repositori.

## 3. Model Kepemilikan Penuh (Prinsip yang Tidak Dinegosiasikan)

1. **Satu paket utuh** — tanpa tier, tanpa fitur terkunci di balik harga.
2. **Self-hosted di infrastruktur pemilik bisnis** — kode & database di server pemilik; tanpa ketergantungan runtime pada cloud vendor.
3. **Kedaulatan data** — ekspor penuh selalu tersedia; backup terjadwal + offsite ke penyimpanan milik pemilik.
4. **Tanpa biaya per user/per cabang** — pertumbuhan cabang hanya soal kapasitas infrastruktur.
5. **Pembaruan terkendali** — setiap perubahan lewat gerbang: audit → desain → implementasi → test/CI → review → rilis atas keputusan pemilik. Tidak ada auto-update senyap.
6. **Kebebasan keluar** — skema terdokumentasi; pemilik dapat memigrasikan/memfork kapan pun.

## 4. Visi & Pengguna

> **Lentera: sistem toko optik selengkap SaaS mahal — yang benar-benar dimiliki pemiliknya.**

Pengguna: satu bisnis optik (satu pemilik, banyak cabang) dengan peran Owner, Admin, Kasir, Gudang, staf Cabang, Refraksionis, dan Teknisi Lab. Bahasa antarmuka: Bahasa Indonesia.

## 5. Prinsip Rekayasa (diwarisi dari standar yang terbukti)

- **Audit-first:** audit → bukti → desain → implementasi → test → verifikasi → rilis. Tidak ada fitur dibangun dari asumsi.
- **Per modul, bergerbang:** satu modul = satu tujuan koheren, selesai + teruji + terverifikasi sebelum modul berikutnya.
- **Otorisasi di batas server:** platform/pemilik ≠ pelaku bisnis otomatis; isolasi cabang ditegakkan server-side (akses global vs cabang yang dikelola), tidak pernah mengandalkan UI.
- **Siklus hidup tanpa hapus:** master data → aktif/nonaktif; transaksional → cancel/void. Koreksi keuangan lewat jurnal balik.
- **Integritas finansial & stok:** transaksi DB, kunci baris pada titik rawan, anti dobel (pembayaran, penerimaan), konsistensi stok ↔ jurnal selalu dapat direkonsiliasi.
- **Invarian domain optik:** resep adalah sumber kebenaran ukuran; **PDR/PDL hanya ada di resep, tidak pernah di blok precal** (precal 11 parameter) — aturan mutlak yang terbukti di Artesis, dibawa utuh.

## 6. Ruang Lingkup MVP (v1.0 — Fondasi Operasional)

MVP adalah inti yang membuat Lentera dapat menjalankan toko optik sungguhan dari hari pertama — spesifikasi perilakunya mengikuti yang terbukti di Artesis:

- **Master data & akses:** cabang, user & peran, produk (frame/lensa/lensa RX/softlens/aksesori), pelanggan, resep, supplier, asuransi; pagar cabang server-side; impor stok awal Excel khusus peran tertinggi.
- **Penjualan & resep:** transaksi penjualan menempel resep; precal 11 parameter; pembayaran; status proses lensa dengan penyelesaian ('Complete') + durasi hari yang terkunci ke waktu selesai; dokumen cetak (orderan/resep/invoice) format konsisten.
- **Pembelian ujung ke ujung:** Purchase Request → Purchase Order → penerimaan (receipt) → pembayaran tunggal & **kolektif (panel AP)** → jurnal akuntansi otomatis.
- **Stok & distribusi:** pergerakan stok ber-alasan (termasuk `initial_stock`), transfer distribusi antar cabang dari detail PO, penerimaan di cabang tujuan.
- **Laporan inti:** pembelian (bruto/retur/neto), ringkasan utang supplier, penjualan; ekspor Excel.
- **Dashboard operasional** per peran dengan pagar cabang.

## 7. Modul Lanjutan (dari peta fitur Lensiro)

| Fase | Modul | Isi ringkas |
|---|---|---|
| 1 | **Akuntansi Penuh** | Chart of Accounts (pemetaan selaras Coretax DJP), buku besar, neraca, laba rugi (total & per cabang), arus kas, aset & penyusutan, tutup buku berkunci; payroll (sub-modul, per keputusan owner). Dibangun di atas jurnal MVP. |
| 2 | **QC Faset & Proses Lab** | Checklist QC dapat dikonfigurasi (bawaan 13 titik) oleh teknisi bernama; 'Complete' hanya sesudah QC wajib lulus; pekerjaan lab eksternal lewat rantai PR → PO — PR lahir otomatis dari penjualan untuk kekurangan lensa (RX selalu lewat PR) dan edging baru dimulai sesudah lensa diterima; papan antrean lab. |
| 3 | **Penjualan Diperluas** | DP, split payment, piutang & cicilan pelanggan, dokter perujuk + komisi, PPN inklusif/eksklusif. |
| 3 | **WhatsApp & Otomasi** | Gateway resmi: invoice via WA, reminder pengambilan & after-service, ucapan ulang tahun, lalu inbox dua arah + blast berpagar. Kegagalan gateway tidak pernah menggagalkan transaksi. |
| 3 | **Membership & Loyalitas** | Level + poin (tercatat sebagai liabilitas akuntansi), riwayat refraksi/rekam medis terpadu, follow-up, portal member ringan ber-token. |
| 4 | **Inventaris Lanjut** | Opname terjadwal + selisih ber-alasan; costing FIFO (gerbang audit metode tersendiri); expiry softlens + peringatan; label barcode EAN-13/Code 128 & batch. |
| 4 | **Analitik & API** | Supplier Analytics (lead time, retur), Sales Analytics (terlaris, margin, tren cabang); API publik read-only ber-token. |
| 5 | **Riset: AI & Offline** | Asisten AI bisnis read-only khusus owner (kredensial AI milik pemilik, pagar data di lapisan query); fitting AI tanpa menyimpan foto; spike mode offline kasir (antrean lokal + sinkronisasi aman). Hasil riset: ketiganya DIPUTUSKAN BANGUN oleh owner dan selesai dibangun 2026-10-18. |

## 8. Kebutuhan Non-Fungsional

- **Keamanan:** otorisasi per sumber daya di server; audit log mutasi (siapa/apa/kapan/cabang); sesi & kata sandi mengikuti praktik terbaik Laravel.
- **Backup & pulih:** terjadwal + offsite milik pemilik; uji pulih terdokumentasi sebelum go-live.
- **Kinerja:** daftar & laporan responsif pada volume multi-cabang; laporan berat memakai agregasi.
- **Kepatuhan lokal:** PPN; pemetaan kode akun Coretax (Fase 1); format dokumen & struk 58mm/80mm/A5/A4.
- **Teknologi:** Laravel (tumpukan yang terbukti pada domain ini), monolit modular per modul di atas; satu basis kode, satu deployment per bisnis.

## 9. Roadmap & Gerbang Rilis

Fase 0 (blueprint & fondasi repo) → MVP → Fase 1 → 2 → 3 → 4 → 5, setiap fase melewati rantai gerbang yang sama: audit → desain → implementasi per modul → CI hijau → review → keputusan rilis owner → deploy → smoke test owner. Tidak ada fase yang dilompati; kegagalan gerbang = berhenti dan lapor, bukan jalan pintas.

## 10. Metrik Keberhasilan

- MVP: satu toko uji menjalankan siklus penuh (jual → proses lensa → selesai; PR → PO → terima → bayar kolektif) tanpa selisih stok/jurnal.
- Fase 1: laporan keuangan satu periode rekonsiliasi 100% terhadap jurnal.
- Umum: nol fitur terkunci, nol data keluar dari infrastruktur pemilik, setiap rilis lolos smoke test pemilik di produksi.

## 11. Risiko & Mitigasi

- **Scope membengkak** → fase & gerbang owner; MVP dibatasi ketat pada operasional inti.
- **Kebenaran akuntansi** → test rekonsiliasi berlapis + periode uji paralel sebelum tutup buku pertama.
- **FIFO mengubah nilai persediaan** → keputusan metode di gerbang tersendiri, tidak digabung rilis lain.
- **Duplikasi upaya dengan Artesis** → diterima secara sadar sebagai keputusan produk: Artesis tetap untuk operasional berjalan; Lentera adalah produk bersih jangka panjang. Tidak ada migrasi data yang dijanjikan di PRD ini.
- **AI & privasi** → read-only, owner-only, tanpa simpan foto, kredensial milik pemilik.

## 12. Di Luar Cakupan (Non-Goals)

- Mengutak-atik repositori/produksi Artesis dalam proyek ini.
- SaaS publik multi-tenant dengan pendaftaran mandiri; tier harga; lisensi per cabang/user.
- Hosting data di cloud pihak ketiga di luar kendali pemilik bisnis.
- Aplikasi mobile native (lini produk terpisah).
- Menyalin kode Artesis mentah-mentah — yang dibawa adalah spesifikasi perilaku terverifikasi; keputusan reuse apa pun di luar PRD ini adalah milik owner.

## 13. Keputusan Owner Berikutnya

1. Nama & identitas: **Lentera** — DIPUTUSKAN (dipilih Crong atas mandat owner, dipakai seterusnya).
2. Repositori GitHub: `toromagetan/Lentera` — SELESAI (dibuat owner; push oleh Crong lewat konektor GitHub).
3. Cakupan MVP & prioritas Fase 1 (Akuntansi Penuh) — DIKONFIRMASI & terbangun.
4. Payroll — DIPUTUSKAN masuk **Fase 4** & terbangun.
