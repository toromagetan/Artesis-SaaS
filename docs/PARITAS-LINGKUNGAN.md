# Checklist Paritas Lingkungan (F0.6)

Gerbang paritas sesuai Blueprint A.1 + G.3: lingkungan pengembangan/uji harus setara dengan target produksi (PHP 8.3, MySQL 8.0, ekstensi daftar tertutup) agar hasil test dapat dipercaya. Diperiksa ulang setiap perubahan server dan sebelum rilis pertama.

## Kelas lingkungan — jangan dicampur (penegasan D-01, 2026-11-12)

- **Lingkungan pengembangan & uji** = VM pengembangan yang hasilnya tercatat di bawah (2026-10-17). Hasil pada tabel itu adalah hasil **historis VM pengembangan** — bukan hasil pemeriksaan terbaru dan bukan bukti tentang server mana pun selain VM itu pada tanggal itu.
- **Target produksi** = server yang akan dipilih lewat keputusan topologi **OD-1 (belum diputuskan)**. Pada saat penegasan ini ditulis **tidak ada server produksi yang berjalan** (server uji coba sebelumnya mati pada 2026-11-10).
- **Paritas produksi BELUM terverifikasi.** Lulusnya pemeriksaan di VM pengembangan **tidak** menyatakan lingkungan produksi setara. Paritas produksi wajib diperiksa ulang di server produksi yang sebenarnya, sebagai gerbang sebelum rilis pertama (Blueprint G.3), dengan perintah pemeriksaan ulang di bawah — dan hasilnya dicatat sebagai entri bertanggal baru, bukan dengan mengubah hasil historis.

## Hasil pemeriksaan — 2026-10-17 (VM pengembangan Crong)

| # | Pemeriksaan | Target (Blueprint A.1) | Aktual | Status |
|---|---|---|---|---|
| 1 | Versi PHP | 8.3.x | PHP 8.3.6 (cli) | ✅ |
| 2 | ext-mbstring | termuat | termuat | ✅ |
| 3 | ext-intl | termuat | termuat | ✅ |
| 4 | ext-gd | termuat | termuat | ✅ |
| 5 | ext-zip | termuat | termuat | ✅ |
| 6 | ext-pdo_mysql | termuat | termuat | ✅ |
| 7 | ext-xml | termuat | termuat | ✅ |
| 8 | ext-curl | termuat | termuat | ✅ |
| 9 | ext-fileinfo | termuat | termuat | ✅ |
| 10 | ext-openssl | termuat | termuat | ✅ |
| 11 | ext-json | termuat | termuat | ✅ |
| 12 | ext-bcmath | **TIDAK dipakai** (dilarang, A.1) | tidak termuat & tidak dipanggil (pemindai CI) | ✅ |
| 13 | Versi MySQL | 8.0.x | MySQL 8.0.46 | ✅ |
| 14 | Basis data uji terpisah | `lentera_test` khusus PHPUnit | dibuat; phpunit.xml menunjuk `lentera_test`, suite memakai RefreshDatabase | ✅ |
| 15 | Kerangka | Laravel 13.x (PHP ^8.3) | Laravel Framework 13.37.0 | ✅ |
| 16 | Composer | 2.x | Composer 2.10.3 | ✅ |
| 17 | Node (build aset) | LTS | Node v24.20.0, npm build Vite hijau | ✅ |
| 18 | Ekstensi dinyatakan di composer.json | satu per satu (daftar tertutup) | 10 `ext-*` tercantum di blok require | ✅ |

## Perintah pemeriksaan ulang

```bash
php -v | head -1
php -m | grep -iE '^(mbstring|intl|gd|zip|pdo_mysql|xml|curl|fileinfo|openssl|json)$'
mysql --version
php artisan --version
bash tools/scan-forbidden.sh   # bc*, float-uang, cast float — harus bersih
```

## Catatan lingkungan VM ini

- VM adalah sandbox pengembangan, bukan host deploy; hanya direktori home yang persisten antar-restart. Prosedur pemulihan PHP/MySQL terdokumentasi di AGENTS.md workspace.
- Mirror apt `mirror.cogentco.com` mati (2026-10-17) dan sudah dicoret dari daftar sumber; `apt-get update` wajib dijalankan ulang sesudah restart layanan sebelum memasang paket apa pun.
- Paritas produksi (server target) diperiksa ulang sebagai gerbang sebelum rilis pertama mengikuti Blueprint G.3.
