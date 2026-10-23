# PRD DETAIL — LENTERA: Spesifikasi Menu per Menu

| | |
|---|---|
| Dokumen | PRD Detail (pendamping PRD utama v1.0 di `docs/PRD.md`) |
| Produk | **Lentera** — Sistem Manajemen & POS Toko Optik, model kepemilikan penuh |
| Versi | 1.0 — 17 Oktober 2026 |
| Status | Draf untuk keputusan owner |

**Cara membaca.** Setiap menu dispesifikasikan dengan pola yang sama: **Tujuan** · **Akses** (peran yang boleh) · **Daftar** (kolom tabel, pencarian, filter, urutan bawaan) · **Formulir** (field penting + validasi) · **Aksi** (tombol & akibatnya) · **Aturan kunci** (yang tidak boleh dilanggar sistem). Fase rilis mengikuti PRD utama: **MVP** = rilis pertama; **F1–F5** = fase lanjutan.

**Peran:** Owner (pemilik, seluruh cabang) · Admin (pengelola, izin eksplisit) · Kasir · Gudang · Cabang (staf cabang) · Refraksionis · Teknisi Lab. Prinsip tetap: akses diberikan per peran + pagar cabang di server; platform/pemilik teknis tidak otomatis menjadi pelaku bisnis.

**Peta navigasi (sidebar):**
Dashboard · Penjualan (Transaksi, Pembayaran & Cicilan, Retur) · Pelanggan & Resep (Pelanggan, Resep) · Katalog (Frame, Lensa, Lensa RX, Softlens, Aksesori) · Lab (Antrean Proses, QC Faset) · Pembelian (Purchase Request, Purchase Order, Penerimaan, Pembayaran, Pembayaran Kolektif, Retur, Supplier) · Stok (Stok Cabang, Pergerakan, Transfer Distribusi, Opname, Impor Stok Awal, Label Barcode) · Akuntansi (Chart of Accounts, Jurnal, Buku Besar, Utang & Piutang, Laporan Keuangan, Aset Tetap, Tutup Buku, Payroll & Komisi) · Membership (Member, Poin, Follow-up, Portal Member) · Laporan (Penjualan, Pembelian, Stok, Supplier Analytics, Sales Analytics) · Komunikasi (Gateway WA, Template, Inbox, Kampanye, Log Kirim) · Pengaturan (Cabang, Pengguna & Peran, Asuransi, Dokter Perujuk, Umum & Pajak, Backup, Audit Log, API).

---

## Prinsip Tampilan — Clean, Modern, Elegan (keputusan owner 2026-10-17, mengikat semua menu)

- **Bahasa visual:** bersih (banyak ruang lega, sedikit garis, tanpa ornamen berlebihan), modern (tipografi sans masa kini — Plus Jakarta Sans; sudut membulat halus; bayangan sangat tipis; ikon garis yang konsisten), elegan (palet Lentera: emerald pekat sebagai warna utama, emas hanya sebagai aksen terbatas pada tombol utama & penanda aktif, latar netral terang dominan). Angka uang memakai angka tabular agar rapi sejajar.
- **Detail BUKAN model preview:** setiap layar "Detail" (transaksi, pelanggan, PO, PR, produk, jurnal, transfer, opname, member) adalah **halaman penuh tersendiri dengan rutenya sendiri**, memuat seluruh isi dan aksinya. **Dilarang** menyajikan detail sebagai modal, drawer/slide-over, quick-view, kartu melayang, atau pratinjau ringkas — mengklik baris/nomor di daftar selalu menavigasi ke halaman detail penuh. Satu-satunya pengecualian: dialog konfirmasi singkat untuk aksi berbahaya (itu dialog keputusan, bukan detail).
- **Dokumen cetak** dibuka sebagai dokumen penuh di tab baru (bukan thumbnail/pratinjau mini).
- **Tombol sejajar — seluruh perintah selalu terlihat (keputusan owner 2026-10-22, ADR-018; menggantikan model dropdown 2026-10-17):** dalam satu layar, seluruh perintah dirender sebagai **tombol tersendiri yang terlihat sejajar dalam satu kelompok** (bukan disembunyikan di dropdown "Aksi"/"Cetak"), dan seluruh pilihan dokumen sebagai tombol dokumen berikon printer di kelompok yang sama. Yang boleh berdiri di luar kelompok hanya navigasi murni (Kembali, paginasi, tautan ke halaman detail). Di daftar, aksi per baris berupa deretan tombol kecil sejajar per baris. Tombol "Baru/Buat" pada daftar menjadi tombol primer pertama kelompok itu. Item berbahaya (void, nonaktif, hapus) ditaruh di urutan terakhir kelompok dengan warna merah + tetap wajib konfirmasi dan alasan. Karena itu, semua "Aksi" yang tercantum per menu dalam dokumen ini **dibaca sebagai tombol yang terlihat**, bukan item menu tersembunyi.
- **Judul tidak ganda (keputusan owner 2026-10-17):** judul halaman tampil **tepat satu kali** — tidak boleh muncul bersamaan di topbar dan di body. Tata letak resmi adalah **SIDEBAR** (keputusan owner 2026-10-18, ADR-013 di Blueprint, menggantikan Varian A header ramping pilihan 2026-10-17): navigasi di sidebar kiri dengan menu terklasifikasi 6 kelompok + submenu; judul tinggal di topbar, body langsung berisi konten tanpa mengulang judul; pencarian global selalu di topbar. Pratinjau yang disetujui: `docs/preview/pratinjau-sidebar.html`.
- **Pencarian global selalu di atas (keputusan owner 2026-10-17):** **global search wajib selalu tersedia di header** pada setiap halaman. Cakupannya lintas modul: pelanggan, produk, nomor transaksi, nomor PR/PO — selalu menghormati izin peran dan, untuk data transaksional, pagar cabang (pelanggan & produk adalah master global, ADR-012 — cukup digerbang izin); hasil dikelompokkan per jenis dengan tautan ke halaman detail penuh.
- Kepadatan informasi seimbang — tidak sesak; mode terang adalah bawaan; tampilan tetap rapi di tablet kasir maupun desktop.

## 1. DASHBOARD — MVP
- **Tujuan:** ringkasan keadaan bisnis hari ini dalam sekali pandang.
- **Akses:** semua peran (isi menyesuaikan peran & pagar cabang).
- **Isi:** kartu Omzet Hari Ini, Transaksi Hari Ini, Orderan Dalam Proses (dengan yang melewati estimasi hari disorot), Stok Menipis, Utang Supplier Jatuh Tempo, Piutang Pelanggan; grafik penjualan 30 hari (per cabang untuk Owner); daftar antrean lab ringkas; pengingat follow-up hari ini (F3).
- **Aturan kunci:** angka dashboard selalu hasil agregasi data nyata dengan pagar cabang yang sama seperti laporan — tidak ada angka "kira-kira"; klik kartu menembus ke daftar terkait dengan filter yang sama.

## 2. PENJUALAN
### 2.1 Daftar Transaksi — MVP
- **Tujuan:** mencari & memantau semua transaksi penjualan.
- **Akses:** Owner, Admin, Kasir, Cabang (hanya cabangnya).
- **Daftar:** kolom Nomor, Tanggal, Pelanggan, Cabang, Item (ringkas), Total, Dibayar, Sisa, Status Bayar (Lunas/Sebagian/Belum), Status Proses Lensa (Dalam Proses/Complete/-), Status Transaksi (Aktif/Void). Pencarian: nomor/nama pelanggan. Filter: tanggal, cabang, status bayar, status proses. Urutan bawaan: terbaru di atas. Klik header kolom mengubah urutan.
- **Aksi:** Transaksi Baru; Buka Detail; Ekspor Excel.
- **Aturan kunci:** transaksi tidak dapat dihapus — pembatalan hanya lewat Void ber-alasan (lihat 2.3).

### 2.2 Transaksi Baru (POS) — MVP, diperluas F3
- **Tujuan:** membuat penjualan lengkap: pelanggan → resep → produk → pembayaran.
- **Akses:** Kasir, Cabang, Admin, Owner.
- **Formulir (terminal kasir satu halaman — ADR-019, model Artesis):** Pelanggan dicari async (daftar cepat lewat tautan Pelanggan Baru); Resep tertempel dimuat per pelanggan (untuk lensa RX **wajib** resep pelanggan yang sama); Keranjang item dari pencarian produk async (frame/lensa/lensa RX/softlens/aksesori) dengan Jumlah, Harga (dari master, dapat diubah), Diskon per item; **item Lensa RX membawa panel Precal manual 11 parameter (3.4)** yang boleh dikosongkan saat jual; Diskon transaksi (aturan izin: diskon besar hanya Owner/Admin); Catatan; Dokter pemeriksa; Asuransi (klaim terisi otomatis dari `claim_value` master); footer ringkasan tetap dengan status DP/Lunas turunan dari pembayaran.
- **Pembayaran (MVP):** tunai/debit/QRIS/transfer; status otomatis Lunas/Sebagian. **(F3):** DP dengan sisa menjadi piutang terjadwal; **split payment** beberapa metode dalam satu transaksi; PPN inklusif/eksklusif.
- **Aksi:** Simpan; Simpan & Cetak Orderan; Simpan & Kirim WA (F3).
- **Aturan kunci:** stok berkurang hanya dari cabang transaksi & tercatat sebagai pergerakan; total pembayaran tidak boleh melebihi total (kelebihan = kembalian tunai, bukan selisih diam); transaksi tersimpan mengunci harga & resep saat itu (snapshot) — perubahan master sesudahnya tidak mengubah transaksi lama.

### 2.3 Detail Transaksi — MVP
- **Tujuan:** satu layar kebenaran untuk satu transaksi — **halaman penuh tersendiri** (bukan preview/modal/drawer, sesuai Prinsip Tampilan).
- **Isi:** kepala (nomor, tanggal, pelanggan, cabang, kasir); resep tertempel (ringkasan OD/OS); baris item; rincian pembayaran & riwayat cicilan (F3); status proses lensa + **durasi hari** (berjalan selama proses, **membeku** saat Complete) + tombol **Selesaikan (Complete)**; dokumen: tombol-tombol dokumen sejajar (ADR-018) di sebelah Kembali untuk semua dokumen konteks ini — **Cetak Orderan** (dokumen ada → buka tab baru; belum ada → buat lalu buka), Cetak Resep, Cetak Invoice.
- **Aksi** (tombol sejajar, ADR-018): Tambah Pembayaran; Selesaikan (Complete); Buka Lab (menembus 5.1 untuk orderan ini); Lengkapi Precal per item RX (3.4, bila belum lengkap); Void Transaksi (urutan terakhir, merah, ber-alasan, peran terbatas, membalik stok & jurnal secara otomatis).
- **Aturan kunci:** penyelesaian ulang ditolak sistem; Complete hanya sesudah QC wajib lulus (F2); Void tidak menghapus baris — status berubah & seluruh jejak (stok, jurnal, pembayaran) dibalik dengan dokumen penyeimbang.

### 2.4 Pembayaran & Cicilan (Piutang Pelanggan) — F3 (daftar dasar di MVP)
- **Tujuan:** memantau & menagih sisa bayar pelanggan.
- **Daftar:** Pelanggan, Nomor Transaksi, Total, Terbayar, Sisa, Jatuh Tempo, Umur Piutang, Status. Filter: cabang, umur (lancar/>30/>60/>90 hari).
- **Aksi:** Terima Cicilan (metode, jumlah — menjadi pembayaran parsial sah & terjurnal); Kirim Pengingat WA (F3, tercatat di Log Kirim); Ekspor.
- **Aturan kunci:** cicilan tidak dapat melebihi sisa; setiap penerimaan mengubah status transaksi induk secara konsisten.

### 2.5 Retur Penjualan — MVP
- **Tujuan:** pengembalian barang dari pelanggan dengan jejak penuh.
- **Formulir:** transaksi asal (wajib), item & jumlah retur, alasan, keadaan barang (kembali ke stok / rusak → stok rusak), metode pengembalian dana (tunai/transfer/kredit toko).
- **Aturan kunci:** retur menaikkan stok hanya sesuai keadaan barang; nilai retur mengurangi penjualan & terjurnal balik; retur parsial diperbolehkan, tidak melebihi jumlah terjual.

## 3. PELANGGAN & RESEP
### 3.1 Daftar Pelanggan — MVP
- **Daftar:** Nama, Kontak (WA), Cabang asal, Jumlah Transaksi, Member (F3: level & poin), Terakhir Berkunjung. Pencarian nama/nomor WA. Filter cabang.
- **Aksi:** Pelanggan Baru; Buka Detail; Ekspor.
- **Aturan kunci:** pelanggan tidak dapat dihapus — hanya dinonaktifkan (patuh aturan tanpa-hapus); **pelanggan adalah master GLOBAL (ADR-012): berlaku dan dapat dilayani di semua cabang** — filter cabang pada daftar adalah saringan cabang asal (pendaftaran), bukan pagar; akses digerbang izin `customers.*`, bukan cabang.

### 3.2 Detail Pelanggan — MVP
- **Isi:** profil & kontak; **riwayat resep** (semua resep, kronologis); **riwayat refraksi** (perubahan ukuran dari waktu ke waktu); riwayat transaksi; riwayat pembayaran/cicilan; (F3) level member, saldo poin, riwayat poin; catatan rekam medis (akses terbatas: Owner/Admin/Refraksionis).
- **Aksi:** Resep Baru; Transaksi Baru untuk pelanggan ini; Kirim WA (F3); Nonaktifkan/Aktifkan.

### 3.3 Resep — MVP
- **Tujuan:** mencatat hasil refraksi secara lengkap & menjadi sumber kebenaran ukuran.
- **Formulir (grid OD/OS):** SPH, CYL, AXIS, ADD, PD (PDR/PDL di sini — **di resep inilah tempatnya**), prisma & keterangan bila ada, tanggal periksa, pemeriksa (Refraksionis), pelanggan (wajib tertempel), cabang.
- **Aksi:** Simpan; Gunakan di Transaksi Baru; Cetak Resep (A5); Duplikat sebagai resep baru (untuk periksa ulang — resep lama tidak diubah).
- **Aturan kunci:** resep yang sudah dipakai transaksi tidak dapat diedit (perubahan = resep baru); memilih pelanggan di formulir resep selalu mengisi kaitan pelanggan secara nyata sebelum simpan (pelajaran bug combobox: pilihan harus ter-registrasi, bukan sekadar tampil); **resep berlaku GLOBAL mengikuti pelanggan (ADR-012)** — dapat dipakai transaksi di cabang mana pun; kolom cabang pada resep adalah cabang pemeriksaan (atribut asal).

### 3.4 Precal — data manual per item penjualan (ADR-019, menggantikan model turunan)
- **Tujuan:** mencatat kebutuhan lensa & frame untuk pemesanan/faset, diinput saat penjualan.
- **Isi:** grid **11 parameter manual** per item Lensa RX: `pvr, pvl, a, b, dbl, ed, vd, pa, wrap, de, type_frame` — diinput kasir di terminal POS (panel item), tersimpan pada item penjualan dan tersalin ke item PR saat stok kurang. Precal **terikat frame**, bukan turunan resep; boleh kosong saat jual (penanda "Belum Lengkap") dan dilengkapi kemudian dari detail penjualan (gudang/kasir berizin `sales.update`).
- **Aturan kunci (MUTLAK):** **PDR/PDL tidak boleh muncul di precal dalam bentuk apa pun** — keduanya hanya hidup di resep (3.3). Item Lensa RX mewajibkan resep pelanggan yang sama pada penjualannya.

## 4. KATALOG PRODUK
### 4.1 Frame — MVP
- **Daftar:** Kode/SKU, Nama, Merek, Warna, Harga Jual, Stok Total (semua cabang), Status (Aktif/Nonaktif). Filter: merek, status; pencarian nama/SKU.
- **Formulir:** SKU (unik), nama, merek, warna, ukuran (eye/bridge/temple), harga modal, harga jual, foto (opsional).
- **Aksi:** Baru/Edit; Aktif/Nonaktif (toggle ⏻ — master tidak dihapus); Lihat Stok per Cabang; Cetak Label (F4).

### 4.2 Lensa — MVP
- **Daftar:** SKU, Nama/Jenis (single vision, progresif, dsb.), Indeks, Coating, Harga, Stok, Status.
- **Formulir:** jenis, indeks, coating, diameter, harga modal/jual.
- **Aturan kunci:** lensa stok 0 tetap dapat dijual (kekurangan menjadi kebutuhan pembelian — mengikuti praktik optik nyata) dengan peringatan jelas di POS.

### 4.3 Lensa RX — MVP
- **Daftar:** SKU, Nama, Rentang SPH/CYL yang didukung, Harga, Status.
- **Aturan kunci:** lensa RX selalu menempel pada resep transaksi; rentang di luar dukungan master ditolak dengan pesan jelas.

### 4.4 Softlens — MVP (pelacakan expiry F4)
- **Daftar:** SKU, Nama, Power, Masa Pakai, Harga, Stok, **Tanggal Kedaluwarsa terdekat** (F4: disorot bila < 90 hari).
- **Aturan kunci:** penjualan softlens kedaluwarsa diblokir sistem (F4).

### 4.5 Aksesori — MVP
- **Daftar & formulir:** pola sama seperti Frame (SKU, nama, harga, status aktif/nonaktif).

## 5. LAB & PROSES LENSA
### 5.1 Antrean Proses — MVP
- **Tujuan:** papan kerja semua orderan lensa yang sedang diproses.
- **Daftar:** Nomor Transaksi, Pelanggan, Cabang, Tanggal Masuk, **Hari Berjalan** (durasi berjalan), Estimasi Selesai, Status (Menunggu/Faset/QC/Siap), Teknisi. Urutan bawaan: paling lama menunggu di atas; yang melewati estimasi disorot merah.
- **Aksi:** Buka Detail Transaksi; Tugaskan Teknisi; Selesaikan (menembus aturan Complete di 2.3).

### 5.2 QC Faset — F2
- **Tujuan:** checklist mutu pemotongan/pemasangan lensa (faset) titik demi titik.
- **Isi:** daftar titik QC yang **dapat dikonfigurasi** (bawaan 13 titik, contoh: kesesuaian ukuran resep, centring/PD, axis, ketebalan tepi, kerapian edging, kebersihan, kesesuaian frame, dsb.); setiap titik: Lulus/Gagal + catatan teknis + nama teknisi + cap waktu.
- **Aksi:** Simpan pemeriksaan; Ulangi titik gagal (riwayat percobaan tersimpan, tidak ditimpa).
- **Aturan kunci:** transaksi tidak dapat di-Complete sebelum semua titik wajib Lulus; titik gagal wajib ber-catatan.

### 5.3 Pemesanan Pekerjaan Lab = Rantai PR → PO (koreksi owner 2026-10-18)
- **Tujuan:** memesan pekerjaan/lensa ke lab eksternal TANPA dokumen baru — memakai rantai pembelian yang sudah ada, persis praktik Artesis.
- **Alur:** penjualan lensa yang stoknya kurang (atau lensa RX, selalu) melahirkan **PR otomatis** tertaut nomor transaksi → PR disetujui → menjadi PO ke lab/supplier → penerimaan → lensa tiba di cabang → barulah edging & QC (5.2) dimulai, di gudang atau cabang terkait.
- **Aturan kunci:** TIDAK ada dokumen "order lab" dan TIDAK ada jurnal biaya lab terpisah — biaya pekerjaan adalah harga pokok item PO yang mengalir lewat penerimaan → persediaan → HPP. Status proses lensa di detail penjualan adalah status TURUNAN rantai ini (PR → PO → Sudah Order → Lensa Datang → Lensa Dikirim → Selesai).

## 6. PEMBELIAN
### 6.1 Purchase Request (PR) — MVP
- **Tujuan:** permintaan pembelian dari cabang/gudang sebelum menjadi PO.
- **Daftar:** Nomor PR, Tanggal, Peminta, Cabang, Jumlah Item, Status (Draft/Submitted/Approved/Digabung ke PO/Selesai). Filter status & cabang.
- **Formulir:** item (produk, jumlah, estimasi harga, supplier tujuan bila diketahui), alasan/kebutuhan.
- **Aksi:** Submit; Approve; **Approve + Buat PO** (menembus 6.2 dengan item terisi); Gabung beberapa PR se-supplier menjadi satu PO.
- **Aturan kunci:** PR yang sudah menjadi PO tidak dapat diedit; penggabungan hanya untuk supplier yang sama.

### 6.2 Purchase Order (PO) — MVP
- **Daftar:** Nomor PO, Supplier, Tanggal, Total, Status (Draft/Terkirim/Sebagian Diterima/Lengkap/Batal-Void), Cabang Tujuan.
- **Detail:** baris item (diminta/diterima/sisa), rincian penerimaan, pembayaran terkait, dan panel **Transfer Distribusi** (lihat 7.3) + riwayat distribusinya.
- **Aksi:** Buat dari PR / buat manual; Kirim ke Supplier (cetak PO); Terima Barang (menembus 6.3); Bayar (6.4); Void ber-alasan.
- **Aturan kunci:** penerimaan tidak boleh melebihi pesanan tanpa persetujuan peran di atasnya; PO lengkap otomatis saat semua item diterima penuh.

### 6.3 Penerimaan Barang (Receipt) — MVP
- **Formulir:** PO asal, tanggal terima, item & jumlah diterima, harga aktual (boleh berbeda dari PO — selisih tercatat), nomor surat jalan supplier.
- **Aturan kunci:** stok bertambah pada cabang tujuan PO pada saat penerimaan disimpan; penerimaan mengunci (koreksi lewat penerimaan negatif/retur pembelian, bukan edit); satu PO dapat diterima bertahap.

### 6.4 Pembayaran Supplier (Tunggal) — MVP
- **Tujuan:** membayar satu receipt/PO.
- **Formulir:** receipt/PO, jumlah, tanggal, metode (transfer/tunai/dll.), referensi.
- **Aturan kunci:** pembayaran tidak melebihi sisa tagihan; setiap pembayaran **terjurnal otomatis**; pembayaran tersimpan tidak dapat diedit — koreksi lewat pembayaran pembalik.

### 6.5 Pembayaran Kolektif (Rekap AP) — MVP
- **Tujuan:** membayar banyak tagihan satu supplier sekaligus dari panel Rekap Pembelian (AP).
- **Isi:** pilih supplier → daftar receipt belum lunas (tertua dulu) → masukkan total bayar → sistem mengalokasikan **FIFO** ke receipt tertua; rincian alokasi tampil sebelum simpan dan dapat disesuaikan per receipt.
- **Aturan kunci:** kegagalan di tengah proses tidak menyisakan pembayaran sebagian (satu transaksi DB — pelajaran bug produksi: validasi penuh sebelum transaksi dimulai); semua error menghasilkan pesan ramah, tidak pernah halaman error mentah; total alokasi = total bayar, selalu.

### 6.6 Retur Pembelian — MVP
- **Formulir:** receipt asal, item/jumlah retur, alasan; nilai retur mengurangi tagihan (atau menjadi kredit supplier bila sudah terbayar); stok berkurang sesuai jumlah retur.
- **Laporan terkait:** retur tampil sebagai kolom tersendiri di Laporan Pembelian (bruto + retur = neto).

### 6.7 Supplier — MVP
- **Daftar:** Nama, Kontak, Alamat, Jumlah PO, Total Utang Berjalan, Status aktif.
- **Detail:** riwayat PO & pembayaran, ringkasan utang, kinerja (F4: lead time & tingkat retur untuk Supplier Analytics).

## 7. STOK & GUDANG
### 7.1 Stok per Cabang — MVP
- **Daftar:** Produk, Cabang, Jumlah, Nilai (metode costing aktif), Status stok (aman/menipis/habis). Filter cabang & jenis produk; pencarian produk.
- **Aturan kunci:** stok tidak pernah negatif untuk frame/aksesori (lensa mengikuti aturan 4.2); angka stok selalu dapat ditelusur ke Pergerakan (7.2).

### 7.2 Pergerakan Stok — MVP
- **Tujuan:** buku besar stok — setiap perubahan tercatat dengan alasan.
- **Daftar:** Tanggal, Produk, Cabang, Jenis alasan (`initial_stock`, penjualan, penerimaan, transfer keluar/masuk, retur, opname, koreksi), Jumlah (+/−), Referensi dokumen, Saldo sesudah.
- **Aturan kunci:** pergerakan tidak dapat diedit/dihapus — koreksi hanya dengan pergerakan penyeimbang ber-referensi.

### 7.3 Transfer Distribusi — MVP
- **Tujuan:** mendistribusikan barang dari PO (atau stok cabang) ke cabang lain.
- **Alur:** dari detail PO: tombol **Kirim ke Cabang (Sisa: N)** per item → pilih cabang tujuan & jumlah → dokumen transfer terbentuk (status Terkirim) → cabang tujuan **menerima** (status Diterima) → stok berpindah.
- **Aturan kunci:** jumlah transfer tidak melebihi sisa yang tersedia; transfer yang belum diterima belum mengurangi stok tujuan dan masih tercatat dalam perjalanan; pembatalan hanya sebelum diterima.

### 7.4 Opname — F4
- **Alur:** jadwal opname per cabang → lembar hitung (stok sistem vs fisik) → selisih disetujui Owner/Admin → pergerakan opname terbit ber-alasan.
- **Aturan kunci:** selama opname cabang berjalan, mutasi stok cabang itu dikunci kecuali peran Owner; selisih tidak dapat dihapus, hanya dikoreksi opname berikutnya/koreksi ber-alasan.

### 7.5 Impor Stok Awal — MVP
- **Tujuan:** memasukkan stok pembuka semua cabang dari Excel, satu kali per cabang (atau saat pembukaan cabang baru).
- **Akses:** **hanya peran tertinggi (Owner/Superadmin)** — dipagari policy eksplisit, bukan string izin biasa.
- **Alur:** unduh template (2 sheet: petunjuk + data; kolom cabang memakai **ID/Nama Cabang**) → unggah → validasi penuh & pratinjau (baris salah ditolak dengan daftar kesalahan, tidak ada impor sebagian senyap) → konfirmasi → pergerakan `initial_stock` terbit per baris + jurnal persediaan.
- **Aturan kunci:** impor yang sudah terkonfirmasi tidak dapat diulang untuk cabang yang sama tanpa proses koreksi resmi.

### 7.6 Label & Barcode — F4
- **Isi:** cetak label harga/barcode (EAN-13/Code 128) per produk atau per batch penerimaan; ukuran label umum toko optik.

## 8. AKUNTANSI
### 8.1 Chart of Accounts (CoA) — F1 (fondasi jurnal otomatis sudah di MVP)
- **Daftar:** Kode Akun, Nama, Kelompok (Aset/Liabilitas/Ekuitas/Pendapatan/Beban), Saldo Berjalan, Status.
- **Formulir:** kode (dengan **pemetaan kode selaras Coretax DJP**), nama, kelompok, akun induk.
- **Aturan kunci:** akun yang sudah dipakai jurnal tidak dapat dihapus (nonaktif saja); struktur mengikuti standar akuntansi Indonesia.

### 8.2 Jurnal Umum — MVP (otomatis) + F1 (manual berpagar)
- **Daftar:** Tanggal, Nomor Jurnal, Sumber (Penjualan/Pembelian/Pembayaran/Manual), Keterangan, Total Debit = Kredit, Status (Terbit/Balik).
- **Detail:** baris debit/kredit per akun.
- **Aturan kunci:** jurnal selalu seimbang (sistem menolak tidak seimbang); jurnal terbit tidak dapat diedit — koreksi lewat **Jurnal Balik**; entri manual hanya Owner/Admin akuntansi.

### 8.3 Buku Besar — F1
- **Isi:** per akun per periode: saldo awal, mutasi (dari Jurnal), saldo akhir; menembus ke jurnal sumber.

### 8.4 Utang & Piutang (Buku Pembantu) — MVP dasar, F1 lengkap
- **Utang (AP):** per supplier: tagihan, terbayar, sisa, umur utang; menembus ke Pembayaran Kolektif (6.5).
- **Piutang (AR):** per pelanggan: menembus ke Pembayaran & Cicilan (2.4).
- **Aturan kunci:** angka di menu ini adalah cermin ledger — tidak ada entri langsung dari menu ini.

### 8.5 Laporan Keuangan — F1
- **Isi:** **Neraca**, **Laba Rugi** (total & **per cabang**), **Arus Kas** (sederhana), per periode terpilih; ekspor Excel/PDF.
- **Aturan kunci:** laporan hanya membaca jurnal periode itu; periode terkunci (8.7) menghasilkan angka final yang tidak berubah.

### 8.6 Aset Tetap — F1
- **Daftar:** Nama Aset, Tanggal Perolehan, Nilai, Metode Penyusutan, Akumulasi, Nilai Buku.
- **Aturan kunci:** penyusutan berjalan otomatis per periode dan terjurnal; penjualan/pelepasan aset tercatat sebagai transaksi aset, bukan hapus.

### 8.7 Tutup Buku — F1
- **Alur:** pilih periode → pemeriksaan (jurnal tidak seimbang? transaksi menggantung?) → kunci periode → laporan final.
- **Aturan kunci:** periode terkunci menolak semua posting bertanggal di dalamnya, termasuk dari modul lain; membuka kembali hanya Owner dengan alasan tercatat di Audit Log.

### 8.8 Payroll & Komisi — F1/F3
- **Isi:** gaji per karyawan per periode (terjurnal); **komisi dokter perujuk** dari transaksi (3.4/2.2); **komisi faset/teknisi** dari orderan Complete; komisi penjualan (F3).
- **Aturan kunci:** komisi dihitung dari data final (transaksi Void mengoreksi komisi periode berjalan; periode terkunci → koreksi di periode berikut).

## 9. MEMBERSHIP — F3
### 9.1 Member & Level
- **Isi:** level member (nama, syarat akumulasi belanja, keuntungan: diskon/poin ganda); daftar member dari data pelanggan (3.1) dengan level & saldo poin.
- **Aturan kunci:** naik/turun level otomatis dari aturan, tercatat riwayatnya; keuntungan level tidak berlaku surut.

### 9.2 Poin
- **Pengaturan:** nilai perolehan (mis. 1 poin per RpX), nilai tukar, masa berlaku poin.
- **Riwayat:** per member: perolehan, penukaran, kedaluwarsa, saldo — setiap baris menembus ke transaksi sumber.
- **Aturan kunci:** poin adalah liabilitas — penukaran poin terjurnal (terhubung 8.2); poin dari transaksi Void dibalik.

### 9.3 Follow-up After-Service
- **Daftar:** pelanggan yang jatuh tempo dihubungi (garansi, kontrol ulang, pengambilan, ulang tahun) dengan status (Belum/Sudah Dihubungi) & penanggung jawab.
- **Aksi:** Tandai Dihubungi + catatan; Kirim WA (menembus Komunikasi).

### 9.4 Portal Member (publik, ber-token) — F3
- **Isi untuk pelanggan:** poin & level, riwayat pembelian, status orderan (proses lensa), tanpa perlu akun/kata sandi — tautan unik ber-token dikirim via WA.
- **Aturan kunci:** token hanya membuka data pemiliknya; token dapat dicabut & diterbitkan ulang dari Detail Pelanggan.

## 10. LAPORAN & ANALITIK
### 10.1 Laporan Penjualan — MVP
- **Isi:** per periode/cabang: jumlah transaksi, bruto, diskon, retur, neto, per metode bayar; ekspor Excel.
### 10.2 Laporan Pembelian — MVP
- **Isi:** per supplier/periode: **bruto, kolom Retur, neto** (kontrak tampilan yang sudah disepakati: bukan disembunyikan di dalam angka), sisa utang; ekspor Excel.
### 10.3 Laporan Stok — MVP
- **Isi:** posisi stok per cabang, nilai persediaan, pergerakan per periode, stok menipis/habis; ekspor.
### 10.4 Supplier Analytics — F4
- **Isi:** lead time rata-rata per supplier, tingkat retur, ketepatan penerimaan, nilai pembelian — dasar keputusan memilih vendor.
### 10.5 Sales Analytics — F4
- **Isi:** produk terlaris, margin per kategori, tren per cabang, pelanggan bernilai tinggi.
- **Aturan kunci (semua laporan):** pagar cabang berlaku penuh; angka laporan harus rekonsiliasi dengan sumbernya (transaksi/jurnal/pergerakan) pada periode yang sama; ekspor menghormati filter yang sedang aktif.

## 11. KOMUNIKASI (WHATSAPP) — F3
### 11.1 Pengaturan Gateway
- **Isi:** penyedia gateway resmi, kredensial (tersimpan aman, tidak pernah tampil penuh), nomor pengirim, status koneksi & tombol uji kirim.
### 11.2 Template Pesan
- **Isi:** template invoice, pengingat pengambilan, after-service, ulang tahun, kampanye — dengan variabel (nama, nomor transaksi, dsb.); pratinjau sebelum aktif; persetujuan Owner untuk template kampanye.
### 11.3 Inbox
- **Isi:** percakapan dua arah dengan pelanggan, tertempel ke data pelanggan; penugasan ke staf.
### 11.4 Kampanye / Blast
- **Alur:** pilih segmen (level member/cabang/terakhir berkunjung) → jumlah penerima → pratinjau → **persetujuan Owner** → kirim bertahap dengan batas laju.
- **Aturan kunci:** tanpa persetujuan Owner tidak ada blast; penerima yang menolak (opt-out) tidak pernah dikirimi lagi.
### 11.5 Log Pengiriman
- **Isi:** setiap pesan keluar: waktu, penerima, template/jenis, status terkirim/gagal, pemicu (manual/otomatis/transaksi) — menjadi bukti audit komunikasi.

## 12. PENGATURAN & MASTER SISTEM
### 12.1 Cabang — MVP
- **Daftar:** Nama cabang (cabang tidak memakai kode — identifikasi ID/Nama), Alamat, Kontak, Status aktif.
- **Aturan kunci:** cabang bertransaksi tidak dapat dihapus — nonaktif saja.
### 12.2 Pengguna & Peran — MVP
- **Isi:** daftar pengguna (nama, peran, cabang yang dikelola, status); **matriks izin per peran** (per menu × lihat/buat/ubah/aksi khusus); atur ulang kata sandi.
- **Aturan kunci:** perubahan izin berlaku segera & tercatat di Audit Log; tidak ada pengguna yang dapat menaikkan izinnya sendiri.
### 12.3 Asuransi — MVP
- **Daftar:** nama perusahaan asuransi/BPJS, kontak, status aktif (master tanpa hapus).
### 12.4 Dokter Perujuk — F3
- **Daftar:** nama, klinik, kontak, tarif komisi, total rujukan & komisi berjalan (menembus 8.8).
### 12.5 Pengaturan Umum & Pajak — MVP
- **Isi:** identitas toko (nama, alamat, logo untuk dokumen), format nomor dokumen, tarif **PPN** bawaan & mode (inklusif/eksklusif), ukuran kertas dokumen (58mm/80mm/A5/A4), mata uang & format tanggal.
- **Status terbangun (2026-10-23):** layar `/pengaturan/umum` mengelola tepat kunci yang dibaca kode — identitas toko (nama, alamat, telepon; mengalir ke kepala cetak), tarif PPN bawaan & mode, ambang diskon tinggi, tarif komisi teknisi lab. Format nomor dokumen, mata uang (IDR), dan format tanggal adalah konvensi tetap sistem (bukan pengaturan); ukuran kertas termal dipilih per aksi cetak (58/80mm), dokumen lain A5. Logo dokumen TERBANGUN sebagai pengaturan (unggah PNG/JPG/WebP di Pengaturan Umum; tampil di kepala semua dokumen cetak; dapat dihapus kembali ke kepala teks).
### 12.6 Backup & Pulih — MVP (operasional)
- **Isi:** jadwal backup otomatis, tujuan **offsite milik pemilik** (mis. Drive pemilik), daftar backup + status verifikasi, uji pulih terpandu.
- **Aturan kunci:** backup dianggap berhasil hanya sesudah terverifikasi dapat dibuka; kegagalan backup memberi peringatan di Dashboard Owner.
- **Status terbangun (2026-10-23):** backup manual + pulih dari layar Backup & Restore (Owner, terkonfirmasi DialogKonfirmasi); backup OTOMATIS harian 02:00 dengan retensi 14 berkas otomatis (perintah `lentera:backup-otomatis`, teraudit). Unggahan offsite (Drive pemilik) MENUNGGU kredensial pemilik (Service Account + Folder ID) — menyalakan sakelar offsite tanpa kredensial mencatat peringatan audit, tidak gagal diam-diam. Status verifikasi keterbacaan kini TERBANGUN (lencana Terverifikasi pada daftar backup; backup dianggap berhasil hanya bila lolos) beserta peringatan Dashboard untuk pemegang izin pengaturan (backup belum ada/basi >48 jam/rusak + job antrean gagal).
### 12.7 Audit Log — MVP
- **Isi:** siapa, melakukan apa, pada data apa, kapan, dari cabang mana — untuk mutasi penting & aksi sensitif (izin, tutup buku, impor, void, pembayaran).
- **Aturan kunci:** audit log hanya dapat dibaca peran tertinggi dan tidak dapat diedit dari aplikasi.
### 12.8 API Read-Only — F4
- **Isi:** token API per keperluan (nama, cakupan baca, terakhir dipakai), cabut token; semua pemakaian tercatat.
- **Aturan kunci:** API tidak pernah dapat menulis; token dicabut berlaku seketika.

## 13. FITUR LANJUT (terbangun di F5 — ringkas; rincian di PRD utama & Blueprint)
- **Asisten AI Bisnis:** tanya-jawab bahasa alami atas data toko, khusus Owner (pagar peran di controller + service), hanya baca lewat daftar-putih maksud, setiap jawaban menyertakan dasar datanya, kredensial AI milik pemilik diatur di Pengaturan (terenkripsi); data toko tidak dikirim ke penyedia.
- **Fitting AI:** ukur PD/dimensi wajah dari kamera perangkat (pemrosesan di perangkat), ranking frame dari stok cabang, rekaman fitting pada data klinis pelanggan (teraudit); hasil ukur wajib dikonfirmasi manual sebelum dipakai.
- **Mode Offline Kasir:** antrean transaksi lokal saat internet mati dengan sinkronisasi & konflik stok yang aman (pengecualian sinkron ditinjau dari antrean, teraudit saat dibuang).
- **Garansi & Stok Rusak (selaras Artesis):** klaim garansi per item penjualan dengan penyelesaian dari stok atau PO, inspeksi stok rusak menjadi penyesuaian stok; daftar & detail terpagar cabang penjualan asal.
- **Insentif (selaras Artesis, ADR-017):** basis per item atas penjualan Lunas, penerima staf cabang + kepala optik, persetujuan periode, clawback otomatis periode berikutnya, ekspor Excel; tanpa jurnal akuntansi.

---
*Akhir PRD Detail v1.0. Perubahan atas dokumen ini mengikuti gerbang yang sama: keputusan owner → revisi dokumen → commit baru di repositori Lentera.*
