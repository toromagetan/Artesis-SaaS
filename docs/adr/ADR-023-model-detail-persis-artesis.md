# ADR-023 — Model Produk Detail Persis Artesis (Menggantikan ADR-015)

| | |
|---|---|
| Status | **Accepted** |
| Tanggal keputusan | 14 November 2026 (ditetapkan pemilik proyek) |
| Konteks pekerjaan | Program adopsi produk A1–A7, PR #51–#57 `toromagetan/Artesis-SaaS` |
| Dokumen terkait | `docs/BLUEPRINT.md` (register ADR, Bagian F & J) · ADR-015 (digantikan) · ADR-022 (batas kepercayaan, tidak berubah) |

## 1. Konteks dan masalah

Pada 13 November 2026 malam pemilik proyek menyampaikan tiga keluhan atas
katalog SaaS: (1) model induk produk (ADR-015: `product_parents` → varian pada
satu tabel `products`) membuat pengelolaan produk terasa rumit; (2) modul impor
produk ala Artesis belum ada; (3) menambah produk menimbulkan error (dua bug
500 sibling, diperbaiki terpisah sebagai PR #50).

Pada 14 November 2026 pemilik proyek memutuskan: **"Produk buat persis Artesis
saja"** (Opsi A; opsi yang lebih ringan ditolak), ditambah empat ketetapan
lanjutan pada hari yang sama:

- matriks stok lensa persis Artesis ikut diadopsi;
- susunan menu dibuat mirip Artesis, fitur khas SaaS masuk kelompok Artesis
  yang paling sesuai temanya, dan Accounting menjadi menu utama sendiri;
- **semua operasional SaaS per tenant** (kaidah OD-2 berlaku menyeluruh);
- **skema FIFO dipertahankan** — yang diadopsi adalah model produk Artesis,
  bukan skema stoknya; produk berkedaluwarsa adalah softlens dan cairan
  softlens.

## 2. Keputusan

Model katalog menjadi persis Artesis:

```
product_categories (5 kategori tetap, tanpa UI kelola)
  → products          — master ringan: category_id, brand, name, is_active
    → detail per tipe — tabel sendiri, id sendiri, SKU per detail:
        frame_details · lens_details · rx_details ·
        softlens_details · accessories_details
```

- **Unit jual dan unit stok adalah DETAIL**, bukan master dan bukan baris
  produk tunggal. Seluruh rujukan transaksional (penjualan, pembelian,
  penerimaan, gerakan stok, lapisan FIFO, opname, transfer, garansi, label,
  insentif, laporan) menunjuk detail lewat pasangan morph
  (`detail_type` alias frame/lens/rx/softlens/accessory + `detail_id`).
- **SKU unik per tenant** pada setiap tabel detail (OD-2: tidak ada operasi
  katalog bersama antar-tenant).
- **FIFO dipertahankan utuh**: lapisan FIFO, gerakan stok, dan valuasi tidak
  berubah mekanismenya; hanya dirujuk-ulang dari produk ke detail. Tanggal
  kedaluwarsa tetap hidup per batch di `receipt_items.expiry_date`; penanda
  `expiry_tracked` menjadi **kolom boolean di `softlens_details` dan
  `accessories_details`** (cairan softlens adalah aksesoris).
- Migrasi `000060` mengonversi model lama di tempat: **id detail = id produk
  lama**, sehingga rujukan historis tidak berubah nilainya; tabel
  `product_parents` dan model dua tingkat ADR-015 dihapus.
- Permukaan pengelolaan mengikuti Artesis: satu halaman daftar per tipe untuk
  kelola (buat/ubah lewat dialog formulir pada daftar itu); master lahir dan
  berganti nama dari formulir detail; halaman detail tetap halaman penuh
  ber-rute sendiri (ADR-018 tidak berubah).
- Impor produk Excel/CSV persis Artesis (validasi seluruh baris dahulu,
  atomik, SKU duplikat per tenant ditolak, stok tidak berubah) dan impor stok
  awal yang dapat dikerjakan pemilik sendiri (XLSX/CSV, pratinjau → komitmen).
- Matriks lensa: model matriks ADR-015 (rentang lengkap langkah 0,25, tiga
  bentuk SV/Progresif/Bifokal) dipertahankan dan kini berjalan di atas
  `lens_details`; selain sebagai alat bantu buat PO dan opname, matriks
  **juga menjadi halaman stok tersendiri** (`/stok/matriks-lensa`) — klausul
  ADR-015 "matriks dibangun BUKAN sebagai menu" digantikan oleh keputusan
  14 November 2026.
- Sidebar disusun ulang mencerminkan pengelompokan & urutan menu Artesis
  (modul A6); tidak ada perubahan rute, izin, atau gerbang fitur oleh
  penyusunan ulang itu.

## 3. Pelaksanaan (program adopsi A1–A7)

| Modul | Isi | PR | Main sesudah merge |
|---|---|---|---|
| A1 | Skema detail + model + seluruh layanan dirujuk-ulang ke detail | #51 | `6e186e44` |
| A2 | Katalog satu halaman per tipe; permukaan induk dihapus | #52 | `bc1b9d22` |
| A3 | Halaman matriks stok lensa + ekspor XLSX | #53 | `2e2b1373` |
| A4 | Impor produk Excel/CSV persis Artesis (+ `XlsxReader`) | #54 | `ee9dd81d` |
| A5 | Impor stok awal XLSX untuk pemilik (jalur self-serve diperbaiki) | #55 | `538a2053` |
| A6 | Sidebar cermin Artesis | #56 | `8937be3e` |
| A7 | ADR ini + penyelarasan dokumen & verifikasi ekspor/backup/seeder | #57 | — |

Setiap modul melewati gerbang yang sama: suite penuh lokal hijau di MySQL
nyata, Pint bersih, CI 5/5, dan merge hanya atas kata pemilik proyek.

## 4. Yang digantikan dan yang tetap berlaku dari ADR-015

**Digantikan:** model dua tingkat induk → varian pada satu tabel `products`;
tabel `product_parents`; klausul "matriks bukan menu".

**Tetap berlaku (dibawa ke ADR ini):** lima kategori produk tetap sebagai
referensi tanpa UI kelola; gambar produk (kini per detail:
`image_path` pada tabel detail); model matriks lensa (rentang lengkap
langkah 0,25, tiga bentuk menurut jenis lensa); matriks sebagai alat bantu
pada alur buat PO dan hitung opname.

## 5. Verifikasi permukaan terkait (A7)

- **Ekspor data per tenant (B-03):** kelima tabel detail terklasifikasi
  sebagai anak `products` pada registry `TenantExportService`, dan langkah
  khusus morph atribut detail (`product_detail_attributes`) tercakup —
  ekspor tenant tidak memuat detail tenant lain.
- **Backup instalasi:** bersifat menyeluruh per tabel (agnostik model),
  sehingga tabel detail ikut serta tanpa perubahan; manifest integritas
  (jumlah baris + sha256 per tabel) mencakup tabel-tabel baru.
- **Seeder:** tidak ada seeder repositori yang menanam produk; satu-satunya
  seeder katalog adalah `ProductCategorySeeder` (lima kategori tetap) —
  tidak terpengaruh perubahan model.

## 6. Konsekuensi & sisa risiko

- Rujukan morph membuat query lintas tipe selalu menyebut pasangan
  (`detail_type`, `detail_id`); tidak ada lagi FK tunggal ke `products`
  untuk baris transaksional — integritas dijaga di lapisan layanan dan
  oleh test isolasi per tenant.
- Konversi data garansi pada migrasi `000060` terlatih lewat test layanan,
  bukan test migrasi khusus (tercatat sejak A1).
- Peta rute pada Blueprint Bagian F dan klasifikasi tabel Bagian J telah
  diselaraskan pada modul A7; dokumen di luar repositori (blueprint adopsi
  2026-11-14) bersifat riwayat program, bukan kontrak berjalan.
