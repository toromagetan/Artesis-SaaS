# Pola Policy & Pagar Cabang Lentera

Dokumen pola untuk F0.3. Kontraknya mengikat semua modul berikutnya
(Blueprint A.3 butir 5, ADR-007: isolasi cabang = global scope + policy +
konteks aktor; UI bukan pagar).

## Lapisan otorisasi (berurutan, semua di server)

1. **Autentikasi** — rute di belakang middleware `auth`. Pengguna nonaktif
   dikeluarkan oleh `EnsureUserIsActive`.
2. **Izin (registry)** — rute/aksi diperiksa terhadap string izin kanonis
   (`EnsurePermission` / `$user->hasPermission()`). String di luar
   `config/permissions.php` = bug; `PermissionRegistry::assertExists`
   membuatnya gagal keras.
3. **Policy per sumber daya** — satu Policy per model bisnis di
   `app/Modules/<Modul>/Policies/`, didaftarkan eksplisit di
   `AppServiceProvider` (`Gate::policy(...)`, karena namespace modul tidak
   ditebak Laravel). Policy menjawab pertanyaan sumber daya yang tidak bisa
   dijawab string izin: milik siapa, cabang mana, aturan diri sendiri.
4. **Pagar cabang** — model bercabang memakai trait
   `App\Support\Concerns\BranchScoped` (global scope `BranchScope`).
   `branch_id` pada query bisnis hanya berasal dari
   `App\Modules\Identity\Services\BranchContext`, tidak pernah dari input
   request mentah (dijaga `ArchitectureTest`).

## Aturan yang selalu berlaku

- Aktor `is_platform` bukan pelaku bisnis: `User::hasPermission()` menolak
  semua izin di luar kelompok `platform`, dan `BranchContext` memberinya
  nol cabang. Kebijakan tidak perlu mengulang pemeriksaan ini.
- Pengguna nonaktif tidak memiliki izin apa pun.
- Owner (peran) = akses global semua cabang; selain itu hanya cabang di
  `user_branch`.
- Penolakan di policy membalas `false` (Laravel mengubahnya menjadi 403),
  bukan pengecualian khusus — kecuali service melempar
  `AuthorizationException` dari `BranchContext::assertCanAccess()` untuk
  alur non-HTTP.

## Contoh

`UserPolicy` (modul Identity) adalah contoh kanonis: izin `users.manage`
untuk semua aksi + aturan sumber daya "tidak dapat menonaktifkan diri"
pada `deactivate()`. Setiap modul baru menyalin pola ini: izin dulu,
aturan sumber daya sesudahnya, tanpa logika cabang manual di controller.
