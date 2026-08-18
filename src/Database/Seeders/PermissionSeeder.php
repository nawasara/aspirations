<?php

namespace Nawasara\Aspirations\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Panel Admin OPD
            'aspirations.report.view',
            'aspirations.report.respond',
            'aspirations.report.dispatch',

            // Verifikasi Kabid — dipisah dari respond, karena satu orang tidak
            // boleh mengerjakan sekaligus menyetujui pekerjaannya sendiri (#16).
            'aspirations.report.verify',

            // Admin Kabupaten — perkecualian saja, bukan pintu masuk.
            'aspirations.report.reject',
            'aspirations.report.reassign',

            // Membuka identitas pelapor anonim. HANYA Inspektorat, dan setiap
            // pembukaan tercatat (#10).
            'aspirations.report.reveal-identity',

            // Kelola kategori
            'aspirations.category.view',
            'aspirations.category.manage',

            // Rekap & dashboard
            'aspirations.report.export',
            'aspirations.dashboard.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'developer')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }

        $this->seedPanelRoles();
    }

    /**
     * Peran untuk panel OPD (`go-webpanel`).
     *
     * Tanpa keduanya, panel menjawab 403 kepada setiap orang kecuali
     * `developer` — endpoint staf memeriksa izin Spatie lewat `can()`, bukan
     * scope API.
     *
     * Diawali `lapor-` supaya jelas milik Lapor Bunda dan tidak tertukar dengan
     * peran OPD milik paket lain yang mungkin menyusul.
     */
    protected function seedPanelRoles(): void
    {
        $roles = [
            // Petugas lapangan: mengerjakan laporan, lalu menyerahkannya.
            //
            // TIDAK diberi `verify`. Itu bukan sekadar pembagian tugas —
            // ReportWorkflow menolak siapa pun yang memverifikasi pekerjaannya
            // sendiri (#16), jadi memberi izin ini hanya menghasilkan tombol
            // yang selalu gagal saat ditekan.
            'lapor-opd-operator' => [
                'aspirations.report.view',
                'aspirations.report.respond',
            ],

            // Kabid: memeriksa hasil kerja, lalu menyetujui atau mengembalikan.
            //
            // `respond` IKUT diberikan dengan sengaja. Kabid di OPD kecil
            // sering merangkap menangani laporan sendiri, dan menutup jalan itu
            // membuat laporan menggantung ketika petugasnya berhalangan.
            // Aturan #16 tetap menjaga: yang ia kerjakan sendiri tidak dapat ia
            // setujui sendiri, siapa pun izinnya.
            'lapor-opd-kabid' => [
                'aspirations.report.view',
                'aspirations.report.respond',
                'aspirations.report.verify',
            ],
        ];

        foreach ($roles as $name => $granted) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            // `givePermissionTo`, BUKAN `syncPermissions`. Admin dapat menambah
            // izin lain lewat panel Nawasara, dan sync akan mencabutnya diam-diam
            // pada setiap deploy.
            $role->givePermissionTo($granted);
        }
    }
}
