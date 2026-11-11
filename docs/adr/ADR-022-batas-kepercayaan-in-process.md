# ADR-022 — Batas Kepercayaan Baca In-Process Tanpa Aktor

| | |
|---|---|
| Status | **Accepted** |
| Tanggal keputusan | 11 November 2026 (ditetapkan pemilik proyek) |
| Konteks pekerjaan | PR #33 `toromagetan/Artesis-SaaS` — audit independen putaran 2–5 |
| Dokumen terkait | `docs/POLA-POLICY.md` (kontrak otorisasi) · `docs/BLUEPRINT.md` Bagian J (arsitektur SaaS) |

> **Catatan penomoran:** dokumen keputusan ini pada awalnya dirujuk sebagai
> "ADR Opsi A". Nomor ADR-020 dan ADR-021 di register Blueprint sudah dipakai
> (lapisan platform SaaS; identitas produk & repositori), sehingga keputusan
> ini ditempatkan sebagai **ADR-022**, nomor berikutnya yang bebas.

## 1. Konteks dan masalah

Lapisan layanan aplikasi dirancang menerima aktor **secara eksplisit sebagai
parameter** dan memvalidasi ember kepemilikannya sendiri di tingkat layanan.
Di sisi lain, `TenantScope`/`BranchScope` adalah pagar query untuk akses
berbasis sesi. Pertanyaan yang harus diputuskan pada audit PR #33: apakah
query model terpagar yang dipanggil **in-process tanpa aktor terautentikasi
dan tanpa konteks sistem** (`TenantContext::asSystem()`) boleh membaca lintas
tenant — ataukah scope harus gagal-tertutup untuk semua baca tanpa aktor?

Bukti biaya alternatif: pemaksaan gagal-tertutup pada semua jalur tanpa auth
pernah dicoba pada putaran pertama PR #33 dan mematahkan **219 test**, karena
ratusan alur layanan yang sah memang berjalan tanpa sesi (antrean, konsol,
listener peristiwa, pemanggilan layanan berparameter aktor dari proses
sistem).

## 2. Opsi yang dipertimbangkan

- **Opsi A — batas kepercayaan in-process diterima formal.** Baca in-process
  tanpa aktor diizinkan; lapisan layanan berparameter aktor adalah penegak
  ember; jalur HTTP tetap gagal-tertutup untuk tamu; tulis global tanpa aktor
  tetap wajib konteks sistem.
- **Opsi B — penegakan konteks eksplisit untuk baca** (`asActor()` + scope
  gagal-tertutup untuk semua baca tanpa aktor). Konsekuensi tercatat: modul
  arsitektur tersendiri yang menyentuh kontrak lapisan layanan secara luas
  dengan putaran test besar (preseden 219 test terdampak).

## 3. Keputusan

**Opsi A ditetapkan oleh pemilik proyek pada 11 November 2026.** Query
`TenantScoped`/`BranchScoped` yang dipanggil in-process tanpa aktor dan tanpa
konteks sistem tetap diperbolehkan membaca lintas tenant, dengan lapisan
layanan sebagai penegak validasi ember, mitigasi pada butir 8, dan pemicu
peninjauan ulang pada butir 10.

## 4. Batas kepercayaan in-process

Alasan batas ini diterima:

- Jalur tanpa aktor yang sah bersifat **tingkat instalasi dan hanya dapat
  dieksekusi dari sisi server**: perintah konsol terjadwal, job antrean
  WhatsApp, dan seeder. Tidak satu pun dapat dipicu dari jaringan tanpa akses
  server (SSH/artisan/worker) — siapa pun yang memiliki akses itu sudah berada
  di dalam batas kepercayaan yang lebih dalam daripada scope query.
- `TenantScope`/`BranchScope` adalah pagar untuk akses berbasis sesi; ia
  bukan satu-satunya, dan bukan lapisan otorisasi layanan.

## 5. Pihak yang berwenang memanggil jalur tersebut

Hanya kode yang berjalan di dalam proses server:

1. Perintah konsol terjadwal (`routes/console.php`) — dijalankan scheduler.
2. Job antrean (`SendWhatsAppMessage`, `SendCampaign`) — dijalankan worker.
3. Seeder — dijalankan operator saat instalasi/pemeliharaan.
4. Layanan aplikasi yang dipanggil dengan argumen aktor eksplisit oleh
   controller terautentikasi atau oleh ketiga jalur di atas.
5. Perkakas operator lokal (tinker) — mensyaratkan akses shell server.

Tidak ada aktor jaringan (browser, API client, webhook) yang berwenang;
semuanya melewati pagar HTTP pada butir 6.

## 6. Relasi dengan TenantContext, BranchContext, konteks sistem, dan penanda tamu

- **HTTP dicegah mencapai jalur tanpa aktor:** middleware `MarkGuestRequest`
  terdaftar pada grup web dan api — setiap request anonim ditandai tamu sejak
  awal, dan `TenantScope`/`BranchScope` gagal-tertutup (hasil kosong) selama
  penanda aktif.
- Penanda hanya dicabut oleh jalur berkredensial non-sesi **sesudah**
  kredensialnya terbukti sah — webhook WA (tanda tangan HMAC), token API,
  token perangkat offline, token portal member — dan jalur itu kemudian
  memasang satu prinsipal teridentifikasi sebagai aktor (dengan pemetaan
  webhook deterministik: unik-atau-yatim).
- Rute publik yang benar-benar anonim (landing, pendaftaran, ganti bahasa)
  tidak membaca model `TenantScoped`.
- **Asimetri tulis vs baca:** tulis global mengubah keadaan bersama secara
  permanen dan senyap, dan pemanggilnya sedikit serta terenumerasi — karena
  itu gerbangnya total: tanpa aktor ⇒ wajib `asSystem()`. Baca tidak mengubah
  keadaan dan pemanggilnya adalah pola penopang seluruh lapisan layanan
  (ratusan titik); menggerbangnya berarti menulis ulang kontrak layanan.
  Prinsipnya: gerbang dipasang sebanding dengan daya rusak dan kelayakan
  enumerasi jalurnya.

## 7. Konsekuensi positif

- Kontrak lapisan layanan yang ada (aktor sebagai parameter) dipertahankan —
  tidak ada penulisan ulang berisiko regresi luas.
- Permukaan yang dapat dieksploitasi dari luar (HTTP + tulis global) sudah
  gagal-tertutup hari ini.
- Keputusan, alasan, dan batasnya terdokumentasi sehingga peninjauan kode
  berikutnya punya acuan yang sama.

## 8. Risiko dan batasan (dinyatakan terbuka)

- Layanan baru yang menerima aktor tetapi **lupa memvalidasi ember** dapat
  membaca lintas tenant di dalam proses. Scope tidak dapat membedakan
  pemanggilan itu dari pemanggilan layanan yang sah — ini keterbatasan nyata
  keputusan ini.
- Keputusan ini **bukan** jaminan isolasi absolut. Ia tidak melindungi dari
  kode berbahaya yang sudah berjalan di dalam proses server, akses database
  langsung, operator infrastruktur, atau kompromi proses itu sendiri.
- Mitigasi yang berlaku: (a) setiap modul wajib membawa test isolasi (aktor
  sah, aktor asing, platform, unassigned, lintas tenant/cabang, jalur HTTP);
  (b) gerbang CI mempertahankan pindai statis + matriks keamanan; (c) pola
  policy/layanan terdokumentasi di `docs/POLA-POLICY.md` sebagai acuan
  peninjauan kode.

## 9. Kualifikasi auditor (mengikat penafsiran keputusan ini)

Dua kualifikasi dari audit independen PR #33 adalah bagian dari keputusan ini
dan mengikat cara dokumen ini — dan dokumen lain — boleh mengutipnya:

1. **Opsi A hanya menjustifikasi jalur in-process murni.** Keputusan ini
   TIDAK boleh dipakai untuk membenarkan endpoint HTTP yang mencabut penanda
   tamu tanpa memasang konteks tenant/prinsipal atau filter eksplisit.
   Endpoint semacam itu adalah pelanggaran terhadap keputusan ini, bukan
   pengecualiannya (dibuktikan pada putaran 3: middleware perangkat offline
   yang mencabut penanda tamu tanpa memasang prinsipal membuka snapshot
   katalog/pelanggan lintas tenant — diperbaiki pada commit `111a3058`
   dengan memasang akun perangkat sebagai aktor permintaan).
2. **`asSystem()` bukan satu-satunya gerbang baca.** `TenantScope`/
   `BranchScope` tidak menegakkan konteks sistem untuk baca, sehingga tidak
   ada dokumen yang boleh mengklaim `asSystem()` sebagai gerbang baca;
   gerbang baca adalah kombinasi penanda tamu pada HTTP dan validasi ember
   pada lapisan layanan, sebagaimana butir 4–6.

## 10. Pemicu peninjauan ulang

Keputusan dibuka kembali bila salah satu terjadi:

1. Kontributor/pengembang eksternal pertama mendapat akses tulis ke repo.
2. API publik diperluas ke pihak ketiga (di luar token perangkat/portal yang
   ada).
3. Keputusan topologi OD-1 berubah (mis. database per tenant).
4. Ditemukan satu saja insiden/bug nyata di mana layanan lupa validasi ember
   dan berdampak lintas tenant.
5. Refaktor besar lapisan layanan berikutnya — momen murah untuk
   memperkenalkan `asActor()` (Opsi B).

## 11. Konsekuensi operasional dan pengujian

- Setiap modul baru yang menyentuh data terpagar wajib membuktikan perilaku
  aktor sah/asing/platform/unassigned/guest pada testnya, termasuk jalur HTTP
  bila modul itu punya permukaan HTTP.
- Perubahan pada middleware penanda tamu, `TenantContext`, atau kedua scope
  wajib menjalankan suite penuh dan diperlakukan sebagai perubahan keamanan.
- Catatan lanjutan di luar keputusan ini (bukan penahan): optimasi performa
  pencocokan nomor webhook & pemuatan ID pelanggan pada inbox — kandidat modul
  tersendiri (indeks nomor ternormalisasi), bukan bagian dari keputusan ini.

## 12. Referensi

- `docs/POLA-POLICY.md` — kontrak otorisasi tenant + cabang yang menerapkan
  keputusan ini.
- `docs/BLUEPRINT.md` — entri ADR-022 pada register ADR; Bagian J.2/J.4.
- Riwayat audit PR #33 (putaran 1–5, 11 Nov 2026) dan merge `c4af9c87`.
