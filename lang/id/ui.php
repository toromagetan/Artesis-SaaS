<?php

/*
 * String antarmuka Lentera (multi-bahasa id/en — ADR-011).
 * Semua teks UI WAJIB lewat kunci di berkas ini (atau lang/*.json untuk
 * string literal bawaan Breeze). Kunci baru ditambah di id DAN en sekaligus.
 */

return [
    'language' => 'Bahasa',
    'search_placeholder' => 'Cari pelanggan, produk, nomor transaksi…',
    'actions' => 'Aksi',
    'print' => 'Cetak',
    'back' => 'Kembali',
    'save' => 'Simpan',
    'save_changes' => 'Simpan Perubahan',
    'search' => 'Cari',
    'active' => 'Aktif',
    'inactive' => 'Nonaktif',
    'activate' => 'Aktifkan',
    'deactivate' => 'Nonaktifkan',
    'edit' => 'Ubah',
    'empty' => 'Belum ada data.',

    'nav' => [
        'dashboard' => 'Dashboard',
        'users' => 'Pengguna',
        'roles' => 'Peran & Izin',
        'branches' => 'Cabang',
        'profile' => 'Profil',
        'logout' => 'Keluar',
    ],

    'auth' => [
        'login' => 'Masuk',
        'login_id' => 'Username atau Email',
        'password' => 'Kata Sandi',
        'remember' => 'Ingat saya',
        'forgot' => 'Lupa kata sandi?',
    ],

    'users' => [
        'title' => 'Pengguna',
        'create' => 'Buat Pengguna',
        'edit' => 'Ubah Pengguna',
        'name' => 'Nama',
        'username' => 'Username',
        'email_optional' => 'Email (opsional)',
        'phone_optional' => 'Telepon (opsional)',
        'password' => 'Kata Sandi',
        'password_new_optional' => 'Kata Sandi Baru (kosongkan bila tidak diganti)',
        'username_immutable' => 'Username tidak dapat diubah.',
        'roles' => 'Peran',
        'branches' => 'Cabang',
        'managed_branches' => 'Cabang yang dikelola',
        'branches_owner_hint' => 'Kosongkan untuk Owner (akses semua cabang).',
        'all_branches' => 'Semua cabang',
        'status' => 'Status',
        'search_placeholder' => 'Cari nama, username, email…',
        'empty' => 'Belum ada pengguna.',
        'created' => 'Pengguna dibuat.',
        'updated' => 'Pengguna diperbarui.',
        'deactivated' => 'Pengguna dinonaktifkan.',
        'activated' => 'Pengguna diaktifkan kembali.',
        'cannot_deactivate_self' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
        'cannot_change_own_roles' => 'Anda tidak dapat mengubah peran Anda sendiri.',
    ],

    'roles' => [
        'title' => 'Peran & Izin',
        'edit' => 'Ubah Izin',
        'role' => 'Peran',
        'permission_count' => 'Jumlah Izin',
        'user_count' => 'Jumlah Pengguna',
        'save' => 'Simpan Izin',
        'updated' => 'Izin peran diperbarui.',
        'cannot_change_own_permissions' => 'Anda tidak dapat mengubah izin peran yang Anda sandang sendiri.',
    ],

    'branches' => [
        'title' => 'Cabang',
        'create' => 'Buat Cabang',
        'edit' => 'Ubah Cabang',
        'name' => 'Nama Cabang',
        'phone' => 'Telepon',
        'phone_optional' => 'Telepon (opsional)',
        'address_optional' => 'Alamat (opsional)',
        'status' => 'Status',
        'empty' => 'Belum ada cabang.',
        'created' => 'Cabang dibuat.',
        'updated' => 'Cabang diperbarui.',
        'activated' => 'Cabang diaktifkan.',
        'deactivated' => 'Cabang dinonaktifkan.',
        'deactivation_blocked' => 'Cabang belum dapat dinonaktifkan: :blockers',
    ],
];
