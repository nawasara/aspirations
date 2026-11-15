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

            // Alihkan laporan ke OPD lain (laporan salah tujuan). Sejak 15 Nov
            // 2026 juga dipegang admin OPD dan Kabid — keputusan Pringgo —
            // lewat ReportWorkflow::reroute(): SLA tidak dimulai ulang, warga
            // diberi tahu, alasan disimpan sebagai catatan internal.
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

            // Menetapkan siapa yang boleh memeriksa, DI DALAM OPD sendiri.
            //
            // Ada supaya penetapan verifikator tidak harus lewat tim Nawasara
            // setiap kali ada mutasi atau rotasi jabatan. Ponorogo punya 21
            // kecamatan dan puluhan OPD; menjadikannya tiket ke tim pusat
            // berarti laporan menggantung sementara SLA terus berjalan.
            //
            // ⚠️ SENGAJA tidak mencakup penautan keanggotaan OPD. Bila admin
            // OPD dapat menarik pegawai ke dalam OPD-nya, ia sekaligus menarik
            // seluruh laporan yang terlihat olehnya — persis pintu yang
            // ditutup rapat oleh sifat fail-closed MembershipResolver.
            'aspirations.verifier.manage',
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
                'aspirations.report.reassign',
            ],

            // Admin OPD: menetapkan dan mencabut Kabid pemeriksa di OPD-nya
            // sendiri lewat halaman "Kelola Pemeriksa" panel (15 Nov 2026).
            //
            // Sebelumnya `verifier.manage` hanya dimiliki `developer`, sehingga
            // halaman itu menjawab 403 kepada semua orang di OPD dan setiap
            // mutasi Kabid tetap menjadi tiket ke tim pusat — persis yang
            // hendak dihindari StaffOpdMemberController.
            //
            // `respond` ikut: admin OPD sering meneruskan laporan yang dimulai
            // petugasnya. TIDAK diberi `verify` — admin bukan pemeriksa; bila
            // ia juga Kabid, berikan kedua peran.
            //
            // Cakupan tetap aman: endpoint dibatasi ke OPD pemegang izin oleh
            // MembershipResolver, dan hanya peran `lapor-opd-kabid` yang dapat
            // diberikan. Penautan pegawai ke OPD tetap milik admin Registry.
            'lapor-opd-admin' => [
                'aspirations.report.view',
                'aspirations.report.respond',
                'aspirations.report.reassign',
                'aspirations.verifier.manage',
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
