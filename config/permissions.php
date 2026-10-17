<?php

/*
 * Registry izin Lentera (Blueprint Bagian C.1) — SUMBER KEBENARAN TUNGGAL.
 * String izin di luar daftar ini = bug. PermissionRegistry membaca file ini;
 * seeder menanamnya ke tabel permissions; UI Peran hanya menampilkan daftar ini.
 * Setiap string: 'modul.aksi' => label Bahasa Indonesia untuk UI matriks peran.
 */

return [
    'dashboard' => [
        'label' => 'Dashboard',
        'permissions' => [
            'dashboard.view' => 'Lihat dashboard',
        ],
    ],
    'sales' => [
        'label' => 'Penjualan',
        'permissions' => [
            'sales.view' => 'Lihat penjualan',
            'sales.create' => 'Buat penjualan',
            'sales.update' => 'Ubah penjualan',
            'sales.void' => 'Void penjualan',
            'sales.pay' => 'Terima pembayaran penjualan',
            'sales.discount.high' => 'Diskon di atas ambang',
            'sales.return' => 'Retur penjualan',
        ],
    ],
    'customers' => [
        'label' => 'Pelanggan',
        'permissions' => [
            'customers.view' => 'Lihat pelanggan',
            'customers.create' => 'Buat pelanggan',
            'customers.update' => 'Ubah pelanggan',
            'customers.deactivate' => 'Nonaktifkan pelanggan',
        ],
    ],
    'clinical' => [
        'label' => 'Klinis (Resep)',
        'permissions' => [
            'prescriptions.view' => 'Lihat resep',
            'prescriptions.create' => 'Buat resep',
            'prescriptions.print' => 'Cetak resep',
            'medical_notes.view' => 'Lihat catatan rekam medis',
        ],
    ],
    'catalog' => [
        'label' => 'Katalog Produk',
        'permissions' => [
            'products.view' => 'Lihat produk',
            'products.create' => 'Buat produk',
            'products.update' => 'Ubah produk',
            'products.deactivate' => 'Nonaktifkan produk',
        ],
    ],
    'lab' => [
        'label' => 'Lab & QC',
        'permissions' => [
            'lab.view' => 'Lihat antrean lab',
            'lab.assign' => 'Tugaskan teknisi',
            'lab.qc' => 'Isi pemeriksaan QC',
            'lab.complete' => 'Selesaikan proses (Complete)',
            'lab.order.manage' => 'Kelola order lab eksternal',
        ],
    ],
    'purchasing' => [
        'label' => 'Pembelian',
        'permissions' => [
            'pr.view' => 'Lihat Purchase Request',
            'pr.create' => 'Buat Purchase Request',
            'pr.approve' => 'Setujui Purchase Request',
            'po.view' => 'Lihat Purchase Order',
            'po.create' => 'Buat Purchase Order',
            'po.update' => 'Ubah Purchase Order',
            'po.void' => 'Void Purchase Order',
            'receipts.create' => 'Terima barang (receipt)',
            'payments.create' => 'Bayar pembelian (tunggal)',
            'payments.collective' => 'Bayar pembelian kolektif',
            'purchases.return' => 'Retur pembelian',
        ],
    ],
    'inventory' => [
        'label' => 'Inventaris',
        'permissions' => [
            'stock.view' => 'Lihat stok',
            'stock.transfer' => 'Kirim transfer stok',
            'stock.receive_transfer' => 'Terima transfer stok',
            'stock.opname' => 'Stok opname',
            'stock.import_initial' => 'Impor stok awal (dipagari policy khusus)',
            'stock.correct' => 'Koreksi stok ber-alasan',
        ],
    ],
    'accounting' => [
        'label' => 'Akuntansi',
        'permissions' => [
            'accounts.view' => 'Lihat bagan akun',
            'accounts.manage' => 'Kelola bagan akun',
            'journals.view' => 'Lihat jurnal',
            'journals.manual' => 'Jurnal manual',
            'journals.reverse' => 'Jurnal balik',
            'reports.financial' => 'Laporan keuangan',
            'periods.lock' => 'Kunci periode',
            'periods.unlock' => 'Buka kunci periode',
            'assets.manage' => 'Kelola aset tetap',
            'payroll.manage' => 'Kelola payroll & komisi',
        ],
    ],
    'membership' => [
        'label' => 'Membership',
        'permissions' => [
            'members.view' => 'Lihat member',
            'members.manage' => 'Kelola member & level',
            'points.adjust' => 'Penyesuaian poin manual (ber-alasan)',
        ],
    ],
    'reports' => [
        'label' => 'Laporan',
        'permissions' => [
            'reports.sales' => 'Laporan penjualan',
            'reports.purchases' => 'Laporan pembelian',
            'reports.stock' => 'Laporan stok',
            'reports.analytics' => 'Analitik',
            'reports.export' => 'Ekspor laporan (Excel)',
        ],
    ],
    'comms' => [
        'label' => 'Komunikasi (WhatsApp)',
        'permissions' => [
            'wa.send' => 'Kirim pesan WhatsApp',
            'wa.inbox' => 'Lihat inbox WhatsApp',
            'wa.templates' => 'Kelola template WhatsApp',
            'wa.campaigns' => 'Buat kampanye WhatsApp',
            'wa.campaigns.approve' => 'Setujui kampanye WhatsApp',
        ],
    ],
    'platform' => [
        'label' => 'Platform & Pengaturan',
        'permissions' => [
            'users.manage' => 'Kelola pengguna',
            'roles.manage' => 'Kelola peran & izin',
            'branches.manage' => 'Kelola cabang',
            'settings.manage' => 'Kelola pengaturan umum',
            'audit.view' => 'Lihat audit log',
            'backups.manage' => 'Kelola backup',
            'api_tokens.manage' => 'Kelola token API',
        ],
    ],
    'master' => [
        'label' => 'Master Data',
        'permissions' => [
            'suppliers.manage' => 'Kelola supplier',
            'insurances.manage' => 'Kelola asuransi',
            'doctors.manage' => 'Kelola dokter perujuk',
        ],
    ],
];
