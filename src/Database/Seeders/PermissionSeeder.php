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
    }
}
