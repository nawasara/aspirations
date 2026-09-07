<?php

$prefix = 'nawasara-aspirations';

/*
| Sidebar Lapor Bunda — dikelompokkan per seksi, mengikuti pola nawasara/hibah.
|
| Sebelumnya empat menu berjejer datar tanpa pengelompokan, sehingga
| "Pengaturan Lapor" duduk sejajar dengan "Laporan Masuk" seolah keduanya
| pekerjaan sehari-hari yang setara. Dengan seksi, yang dikerjakan tiap hari
| terpisah dari yang disetel sekali lalu ditinggalkan.
|
| Penanda seksi = entri submenu TANPA `url`, memakai kunci `section`.
| Didukung sidebar nawasara-ui; paket yang tidak memakainya tidak terpengaruh.
|
| ⚠️ Workspace id `ponorogo-hub` DIBAGI dengan nawasara/citizen.
| WorkspaceManager menggabungkan entri ber-id sama dan mengambil label + icon
| dari yang termuat LEBIH DULU secara alfabet — dan `nawasara-aspirations`
| berurutan sebelum `nawasara-citizen`. Jadi berkas ini yang menentukan judul
| seluruh workspace: label dan icon HARUS tetap sama persis dengan yang ada di
| nawasara/citizen, atau judul sidebar berubah diam-diam.
|
| ⚠️ `group` harus salah satu dari WorkspaceManager::GROUP_ORDER. Selain itu
| mendarat di "Lainnya" tanpa peringatan.
*/

return [
    [
        'workspace' => 'ponorogo-hub',
        'label' => 'Ponorogo Hub',
        'icon' => 'lucide-landmark',
        'group' => 'Layanan',
        'url' => '',

        // TANPA permission di level workspace — penggerbangan sesungguhnya ada
        // di tiap seksi dan submenu. Mengisinya di sini berarti staf yang hanya
        // berhak atas satu bagian tidak melihat workspace-nya sama sekali,
        // karena accessible() menyaring dengan satu permission ini saja.
        'permission' => null,
        'submenu' => [
            [
                'section' => 'Laporan Warga',
                'icon' => 'lucide-megaphone',
                'permission' => 'aspirations.report.view',
            ],
            [
                'label' => 'Ringkasan',
                'icon' => 'lucide-layout-dashboard',
                'url' => url($prefix.'/dashboard'),
                'permission' => 'aspirations.dashboard.view',
                'navigate' => true,
            ],
            [
                'label' => 'Laporan Masuk',
                'icon' => 'lucide-inbox',
                'url' => url($prefix.'/reports'),
                'permission' => 'aspirations.report.view',
                'navigate' => true,
            ],

            [
                'section' => 'Pengaturan',
                'icon' => 'lucide-settings',
                'permission' => 'aspirations.category.view',
            ],
            [
                'label' => 'Kategori Laporan',
                'icon' => 'lucide-tags',
                'url' => url($prefix.'/categories'),
                'permission' => 'aspirations.category.view',
                'navigate' => true,
            ],
            [
                'label' => 'Batas Waktu & Kebijakan',
                'icon' => 'lucide-sliders-horizontal',
                'url' => url($prefix.'/settings'),
                'permission' => 'aspirations.category.manage',
                'navigate' => true,
            ],
        ],
    ],
];
