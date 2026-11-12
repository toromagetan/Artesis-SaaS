# Pola Policy & Pagar Tenant + Cabang — Artesis SaaS

Dokumen pola otorisasi aplikasi. Berasal dari dokumen pola F0.3 (pagar cabang,
era satu bisnis) dan **diperluas pada D-01 (2026-11-12)** menjadi kontrak
otorisasi dua lapis — **tenant** dan **cabang** — sesuai implementasi Fase 0
(PR #23–#35). Kontraknya mengikat semua modul (Blueprint A.3 butir 5, ADR-007
untuk cabang; ADR-022 untuk batas kepercayaan in-process; OD-2 untuk
kepemilikan per tenant). UI bukan pagar — semua penegakan di server.

## Konteks aktor: `TenantContext` (satu-satunya sumber identitas tenant)

`App\Modules\Identity\Services\TenantContext` menyelesaikan konteks dari
aktor di sisi server — tidak pernah dari input request, subdomain, atau
header. Lima mode (nama & semantik persis dari konstanta implementasi):

| Mode (konstanta) | Arti | Kapan terjadi |
|---|---|---|
| `MODE_TENANT` (`'tenant'`) | Aktor adalah pengguna sebuah tenant; `tenantId()` mengembalikan tenant itu | Pengguna dengan `tenant_id` terisi. **Catatan:** mode tenant dihasilkan dari `tenant_id` yang terisi — bila baris tenantnya tidak ditemukan, modenya tetap tenant tetapi konteksnya tidak sah; `isValid()`-lah yang membedakan kedua keadaan itu |
| `MODE_PLATFORM` (`'platform'`) | Aktor platform (`is_platform`) — pengelola SaaS, **bukan** pelaku bisnis tenant | Pengguna dengan `is_platform = true` |
| `MODE_STANDALONE` (`'standalone'`) | Instalasi individual sejati: instansi tanpa satu pun tenant | Pengguna tanpa `tenant_id` pada instansi dengan `Tenant::count() === 0` |
| `MODE_UNASSIGNED` (`'unassigned'`) | Akun tanpa tenant pada instansi yang SUDAH bertenant — keadaan tidak sah untuk bisnis | Pengguna tanpa `tenant_id` pada instansi bertenant |
| `MODE_GUEST` (`'guest'`) | Tidak ada aktor terautentikasi | `TenantContext::for(null)` — terjadi pada request HTTP anonim **dan** pada proses tanpa pengguna (konsol/job tanpa konteks yang dipasang). Pembeda khusus HTTP adalah penanda `isGuestHttp()` (lihat penanda tamu di bawah), bukan mode ini |

Aturan turunan yang mengikat:

- `isValid()` benar **hanya** untuk mode tenant dengan model tenant yang
  benar-benar ditemukan. Pemeriksaan "apakah aktor ini tenant yang sah"
  wajib memakai `isValid()`, bukan sekadar `tenantId() !== null`.
- Mode platform, unassigned, dan guest **tidak** memiliki ember tenant —
  kode bisnis tidak boleh memperlakukan ketiganya sebagai standalone.
- **Konteks sistem** — `TenantContext::asSystem(Closure)` /
  `isSystemContext()`: penanda eksplisit untuk operasi tingkat instalasi
  yang sah (perintah terjadwal, job, seeder). **Tulis global tanpa aktor
  wajib berjalan di dalam konteks sistem**; tanpa penanda itu, penulisan
  pengaturan global ditolak.
- **Penanda tamu HTTP** — `markGuestHttp()` / `isGuestHttp()` (dipasang
  middleware `MarkGuestRequest` di awal request pada grup web & api):
  selama penanda aktif, `TenantScope`/`BranchScope` **gagal-tertutup**
  (hasil kosong). Penanda hanya dicabut jalur berkredensial non-sesi
  sesudah kredensial terbukti — dengan dua mekanisme berbeda: token API
  dan token perangkat offline **memasang pemilik kredensial sebagai
  aktor**; webhook WA (HMAC) dan portal member (token) **tidak memasang
  aktor**, melainkan membatasi pembacaan lewat filter eksplisit
  (pencocokan nomor unik-atau-yatim untuk webhook; pelanggan yang
  ditunjuk token untuk portal). Lihat ADR-022 butir 6 & 9.

## Konteks cabang: `BranchContext`

`App\Modules\Identity\Services\BranchContext` menyelesaikan cabang aktor:

- `managedBranchIds()` — cabang yang dikelola aktor (penugasan `user_branch`
  + aturan peran); inilah ember operasional aktor.
- `companyBranchIds()` — seluruh cabang perusahaan (tenant) aktor; dipakai
  hanya untuk tampilan tingkat perusahaan yang memang berizin.
- `assertCanAccess(branchId)` melempar `AuthorizationException` bila cabang
  di luar ember aktor — termasuk cabang milik tenant lain, karena cabang
  selalu milik satu tenant dan aktor tenant tidak pernah mengelola cabang
  tenant lain.
- `branch_id` pada query bisnis hanya berasal dari `BranchContext`, tidak
  pernah dari input request mentah (dijaga `ArchitectureTest`). Cabang dari
  request selalu divalidasi terhadap ember aktor sebelum dipakai.

## Urutan batas otorisasi (berurutan, semua di server)

Urutan berikut adalah urutan **waktu** pada satu request: penanda tamu
dipasang paling awal, sebelum pemeriksaan rute dan query bisnis apa pun.

1. **Penanda tamu (awal request)** — `MarkGuestRequest` menandai setiap
   request web/api sebagai tamu sejak awal; selama penanda aktif, scope
   gagal-tertutup. Penanda dicabut hanya oleh autentikasi sesi atau jalur
   berkredensial non-sesi yang sah (dua mekanisme pada bagian konteks
   aktor). Request anonim yang tidak pernah terautentikasi tetap bertanda
   tamu sampai akhir; rute publik yang sah (landing, pendaftaran, bahasa)
   tidak membaca model `TenantScoped`.
2. **Autentikasi** — rute di belakang middleware `auth`; jalur berkredensial
   non-sesi memakai middleware tokennya masing-masing.
3. **Status akun & tenant aktif** — `EnsureUserIsActive` mengeluarkan
   pengguna nonaktif; `EnsureTenantIsActive` menolak pengguna yang
   tenant-nya dinonaktifkan platform (S-01).
4. **Identitas aktor** — `TenantContext` menetapkan mode aktor
   (tenant/platform/standalone/unassigned/guest). Aktor platform berhenti
   di gerbang platform: grup rute platform memakai middleware `platform`
   (`EnsurePlatformUser`) + izin platform.
5. **Konteks tenant** — model milik tenant memakai trait
   `App\Support\Concerns\TenantScoped` (global scope
   `App\Support\Scopes\TenantScope`); model bercabang juga memakai
   `BranchScoped`/`BranchScope`. Scope adalah pagar query, **bukan**
   pengganti otorisasi layanan (ADR-022).
6. **Izin (registry)** — rute/aksi diperiksa terhadap string izin kanonis
   (`EnsurePermission` / `$user->hasPermission()`). String di luar
   `config/permissions.php` = bug; `PermissionRegistry::assertExists`
   membuatnya gagal keras. `hasPermission()` berbasis peran untuk semua
   aktor dan menolak pengguna nonaktif; aktor platform hanya dapat menerima
   izin kelompok `platform`.
7. **Policy per sumber daya** — satu Policy per model bisnis di
   `app/Modules/<Modul>/Policies/`, didaftarkan eksplisit di
   `AppServiceProvider` (`Gate::policy(...)`, karena namespace modul tidak
   ditebak Laravel). Policy menjawab pertanyaan sumber daya yang tidak bisa
   dijawab string izin: milik tenant siapa, cabang mana, aturan diri
   sendiri. Contoh mode-aware: `BackupPolicy` (platform pada instansi
   bersama; Owner hanya pada standalone) dan `TenantExportPolicy` (Owner
   untuk tenantnya sendiri; platform wajib `backups.manage`; akun nonaktif
   selalu ditolak).
8. **Konteks cabang & akses cabang** — `BranchContext` memvalidasi cabang
   operasi & cabang objek terhadap ember aktor (`assertCanAccess`).
9. **Konteks sistem** — hanya untuk operasi tingkat instalasi yang memang
   sah (scheduler, job, seeder, perkakas operator): tulis global wajib
   `asSystem()`; baca in-process tanpa aktor mengikuti ADR-022 (layanan
   berparameter aktor memvalidasi embernya sendiri).

## Prinsip yang selalu berlaku

- **Tenant adalah batas kepemilikan & isolasi data bisnis** (OD-2, diputuskan
  10 Nov 2026); cabang adalah batas operasional **di dalam** tenant.
- Data tenant tidak boleh bocor melalui jalur apa pun: query, route model
  binding, berkas, ekspor, laporan, cetakan, maupun relasi antar-model.
  Lapisan layanan yang menerima aktor sebagai parameter wajib memvalidasi
  ember kepemilikan objek terhadap aktor itu sendiri.
- **`branch_id` tidak pernah dipercaya dari input mentah** — selalu lewat
  validasi konteks (butir 7).
- **Master pelanggan, resep, dan produk bersifat global DI DALAM tenant**
  (ADR-012 sebagaimana dikualifikasi OD-2): lintas cabang satu tenant,
  tidak pernah lintas tenant. `customers.branch_id` /
  `prescriptions.branch_id` adalah atribut asal, bukan pagar.
- **Aktor platform bukan otomatis pelaku bisnis tenant**: ia ditolak dari
  aksi bisnis tenant oleh konstruksi izin (butir 5) dan policy; akses
  platform atas nama tenant (mis. ekspor B-03) adalah pengecualian eksplisit
  berizin platform + teraudit, bukan hak bawaan.
- **Konteks sistem bukan jalan pintas otorisasi** — ia penanda operasi
  instalasi yang sah, bukan cara melewati policy/izin pada jalur HTTP.
- **Guest tidak memperoleh akses karena tidak terautentikasi** — ketiadaan
  aktor berarti gagal-tertutup, bukan lolos.
- Pengguna nonaktif tidak memiliki izin apa pun; Owner (peran) = akses semua
  cabang **tenant-nya sendiri**; selain itu hanya cabang di `user_branch`.
- Penolakan di policy membalas `false` (Laravel mengubahnya menjadi 403),
  bukan pengecualian khusus — kecuali service melempar
  `AuthorizationException` dari `BranchContext::assertCanAccess()` untuk
  alur non-HTTP.

## Contoh

- `UserPolicy` (modul Identity) adalah contoh kanonis lapisan izin + sumber
  daya: izin `users.manage` untuk semua aksi + aturan "tidak dapat
  menonaktifkan diri" pada `deactivate()`.
- `TenantExportPolicy` + `TenantExportService` (modul Platform) adalah contoh
  kanonis kepemilikan per tenant untuk data yang keluar sistem: registry
  klasifikasi tabel eksplisit, filter kepemilikan per relasi, pengecualian
  kolom kredensial, dan pencatatan audit untuk unduhan **dan** penolakan.
- Setiap modul baru menyalin pola ini: konteks aktor → izin → policy →
  validasi ember layanan → pagar cabang, tanpa logika tenant/cabang manual
  di controller.

Referensi: [ADR-022 — Batas Kepercayaan In-Process](adr/ADR-022-batas-kepercayaan-in-process.md) ·
Blueprint Bagian J (arsitektur SaaS) · PRD Bagian 2 & 6.
