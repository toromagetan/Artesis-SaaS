# Runbook Deploy Artesis (SaaS POS Optik)

Satu dokumen rujukan untuk memasang dan memperbarui Artesis
(basis Lentera) di server produksi. Ditulis untuk model **server
bersama**: satu server melayani banyak optik (tenant), dikelola
operator platform. Setiap pemasangan WAJIB melewati daftar periksa
smoke test di bagian 9 sebelum dinyatakan tayang.

> Aturan emas: **catat commit yang sedang berjalan SEBELUM deploy**
> (`git rev-parse HEAD`) — itu jangkar rollback Anda. Jangan pernah
> menyatakan deploy berhasil tanpa bukti yang bisa ditanya ulang
> (hash commit di server, hasil smoke test).

---

## 1. Prasyarat server

| Komponen | Kebutuhan |
| --- | --- |
| OS | Ubuntu 24.04 LTS (atau setara) |
| PHP | **8.3+ dengan tambalan keamanan terbaru** (gerbang CVE PHP2Shell: runtime PHP wajib versi tertambal; periksa `php -v` dan catatan rilis PHP) — ekstensi: `mysql, mbstring, xml, curl, gd, intl, zip, bcmath` |
| MySQL | 8.0, **hanya listen localhost** (`bind-address = 127.0.0.1`) |
| Web server | nginx (contoh vhost di `docs/server/nginx-artesis.conf`) |
| Node.js | 20+ hanya di mesin build (aset dapat dibangun di CI/lokal lalu diunggah; server produksi tidak wajib punya Node) |
| Proses latar | cron (penjadwal Laravel) + satu queue worker (systemd, contoh di `docs/server/artesis-queue.service`) |
| TLS | Sertifikat domain (certbot/Let's Encrypt) — aplikasi WAJIB HTTPS |

Kapasitas acuan server bersama 2 vCPU / 4 GB RAM / 60 GB:
nyaman ±8–12 optik (±15–25 cabang). Pengikat utama = RAM, lalu
storage. Batasi ±10 optik per server pada awalnya; pecah ke server
kedua bila RAM jam sibuk > 75% atau disk > 70%.

## 2. Persiapan database

```sql
CREATE DATABASE artesis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'artesis'@'localhost' IDENTIFIED BY '<kata-sandi-kuat>';
GRANT ALL ON artesis.* TO 'artesis'@'localhost';
FLUSH PRIVILEGES;
```

MySQL tidak boleh terekspos ke jaringan publik. Akses root MySQL
di server Ubuntu memakai auth_socket (bawaan) — jangan diubah.

## 3. Pemasangan aplikasi

```bash
cd /var/www
git clone https://github.com/toromagetan/Lentera.git artesis
cd artesis
git checkout main            # catat: git rev-parse HEAD
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Isi `.env` produksi (nilai wajib):

```dotenv
APP_NAME=Artesis
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain-anda>
APP_LOCALE=id
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=artesis
DB_USERNAME=artesis
DB_PASSWORD=<kata-sandi-kuat>
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Lalu:

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
npm ci && npm run build      # atau unggah public/build dari mesin build
php artisan optimize
```

Kepemilikan berkas: direktori `storage/` dan `bootstrap/cache/`
harus dapat ditulis oleh pengguna web server (mis. `www-data`).

Urutan penanaman (`DatabaseSeeder`) menanam: identitas (peran,
izin, pengguna `owner` + `platform`, cabang awal), **paket SaaS +
peran Platform**, akuntansi, templat QC, templat WA, level member,
kategori produk.

## 4. LANGKAH KEAMANAN WAJIB SEGERA SESUDAH SEED

Pengguna bawaan memakai kata sandi `password` — **ganti keduanya
saat itu juga**, sebelum domain diumumkan:

1. `owner` — pemilik instalasi (peran Owner).
2. `platform` — aktor platform SaaS (panel paket/tenant/penagihan).

Ganti lewat menu Profil → Kata Sandi setelah login pertama, atau
`php artisan tinker` dengan `Hash::make(...)`. Periksa juga:

- `APP_DEBUG=false` (halaman debug tidak boleh tampil ke publik).
- `.env` tidak dapat diakses dari web (vhost contoh sudah
  mengarah ke `public/` saja).
- Direktori `storage/app/backups` tidak terekspos web.

## 5. nginx + header keamanan

Pakai contoh `docs/server/nginx-artesis.conf`. Poin yang tidak
boleh hilang:

- `server_tokens off;`
- HSTS: `Strict-Transport-Security: max-age=31536000` —
  **tanpa** `includeSubDomains` sampai semua subdomain siap HTTPS.
- `Referrer-Policy: strict-origin-when-cross-origin`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- FastCGI ke PHP-FPM; dokumen root = `public/`.

Aktifkan TLS (certbot), lalu pastikan `APP_URL` memakai `https://`.

## 6. Penjadwal & antrean (WA, backup, poin)

Aplikasi mengandalkan tugas terjadwal: otomasi WhatsApp (harian
08:00 + sapuan antrean tiap jam), backup otomatis (harian 02:00),
kedaluwarsa poin (harian 00:30). Tambahkan ke crontab pengguna
aplikasi:

```cron
* * * * * cd /var/www/artesis && php artisan schedule:run >> /dev/null 2>&1
```

Antrean (`QUEUE_CONNECTION=database`) diproses worker systemd —
salin `docs/server/artesis-queue.service`, sesuaikan path &
pengguna, lalu:

```bash
sudo systemctl enable --now artesis-queue
```

Sesudah setiap deploy kode: `sudo systemctl restart artesis-queue`
(worker memuat kode lama di memori).

## 7. Backup & offsite

- Backup aplikasi berjalan otomatis tiap hari 02:00 ke
  `storage/app/backups` (menu Pengaturan → Backup untuk unduh/
  restore manual).
- **Backup di server yang sama BUKAN perlindungan.** Salin keluar
  server setiap malam (di luar jam sibuk, bergiliran bila banyak
  instalasi) — contoh rclone ke penyimpanan objek/Drive:

```bash
# crontab, contoh 03:15 — sesuaikan remote & kredensial Anda
15 3 * * * rclone copy /var/www/artesis/storage/app/backups remote:artesis-backups/$(hostname) --max-age 48h
```

- **Uji restore minimal sekali** sebelum tayang dan berkala
  sesudahnya: pulihkan satu berkas backup ke instalasi uji dan
  pastikan aplikasi bisa membaca datanya. Backup yang belum pernah
  diuji restore = belum ada backup.

## 8. Prosedur update (deploy berikutnya)

```bash
cd /var/www/artesis
git rev-parse HEAD            # (1) catat jangkar rollback
git fetch origin && git checkout main && git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force   # (2) migrasi selalu sesudah kode
php artisan view:clear
php artisan optimize
sudo systemctl restart artesis-queue
```

Rollback: `git checkout <jangkar>` lalu ulangi langkah composer →
`view:clear` → restart queue. Migrasi yang destruktif tidak dipakai
di proyek ini (tanpa-hapus adalah aturan produk), sehingga rollback
kode aman; bila migrasi menambah kolom, kolom itu dibiarkan saja.

## 9. Daftar periksa smoke test (WAJIB, sesudah pemasangan & update besar)

Kerjakan dari browser, catat hasilnya. Semua harus lulus:

1. [ ] `https://<domain>/` — landing Artesis tampil (judul "Artesis").
2. [ ] `/login` tampil; login `owner` (kata sandi BARU) berhasil ke dashboard.
3. [ ] `/daftar` tampil; buat satu tenant uji → langsung masuk dashboard tenant, paket **Free** di menu Paket Saya.
4. [ ] Sebagai admin tenant uji: buat 1 produk → tersimpan; Paket Saya menunjukkan pemakaian produk 1/500.
5. [ ] Menu yang terkunci untuk Free (mis. Permintaan Pembelian) menolak dengan 403.
6. [ ] Login `platform`: panel Paket, Tenant, Penagihan terbuka; halaman bisnis (Penjualan) menolak platform (403).
7. [ ] Di Penagihan: isi rekening tujuan → tampil di Paket Saya tenant uji.
8. [ ] Transaksi penjualan uji kecil di tenant uji → struk/invoice tersimpan; stok berkurang benar.
9. [ ] `php artisan schedule:run` di server berjalan tanpa galat; `systemctl status artesis-queue` = active.
10. [ ] Backup manual dari menu Pengaturan terbuat; unduh dan uji buka berkasnya.
11. [ ] Header: `curl -sI https://<domain>/` memuat HSTS + `server` tanpa versi nginx.
12. [ ] Empat bahasa dapat dipilih (ID/EN/MS/ZH) dan halaman berganti bahasa.

## 10. Prasyarat luar (di luar kendali kode)

Item berikut BUKAN bagian deploy teknis dan menunggu keputusan/
input pemilik:

- Server/VPS produksi dibeli & domain diarahkan (DNS) + TLS aktif.
- Kredensial WhatsApp Business (WABA) untuk fitur WhatsApp paket
  Best Offer — tanpa ini, fitur WA tidak dapat dinyalakan.
- Rekening tujuan transfer penagihan diisi di panel Penagihan.
- Tinjauan penutur asli untuk paket bahasa Melayu & Mandarin
  sebelum promosi publik ke wilayah tersebut.
- Keputusan gateway pembayaran (penagihan saat ini transfer manual).
