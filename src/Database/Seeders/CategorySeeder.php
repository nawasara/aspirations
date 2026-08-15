<?php

namespace Nawasara\Aspirations\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\Aspirations\Models\Category;

/**
 * Isi awal 13 urusan dari config.
 *
 * Idempoten: dicocokkan lewat `code`, jadi menjalankannya ulang memperbarui
 * yang ada alih-alih menggandakan. Aman dipanggil di setiap deploy.
 *
 * ⚠️ Seeder ini TIDAK menimpa `sla_hours`, `opd_id`, `name`, dan `hint` pada
 * kategori yang sudah ada. Setelah rilis, keempatnya dikelola lewat panel —
 * menimpanya di setiap deploy akan mengembalikan angka yang baru saja
 * disepakati OPD ke nilai sementara di config, diam-diam.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $urutan = 0;

        foreach ((array) config('nawasara-aspirations.categories', []) as $data) {
            $urutan++;

            $opdId = $this->resolveOpd($data['opd_code'] ?? null);

            if ($opdId === null) {
                // Dilaporkan, bukan didiamkan: kategori tanpa OPD tidak dapat
                // didisposisi otomatis, dan laporan padanya akan berhenti di
                // pintu masuk. Lebih baik ketahuan saat seed.
                $this->command?->warn(
                    "  Kategori [{$data['code']}]: OPD '{$data['opd_code']}' tidak ditemukan di registry — opd_id dikosongkan."
                );
            }

            $ada = Category::where('code', $data['code'])->first();

            if ($ada) {
                // Hanya menyegarkan hal yang bukan kebijakan. Lihat catatan
                // kelas di atas.
                $ubah = [
                    'icon_name' => $data['icon_name'],
                    'color' => $data['color'],
                    'is_sensitive' => $data['is_sensitive'] ?? false,
                ];

                // MENGISI yang masih kosong bukan menimpa keputusan. Kategori
                // yang belum pernah punya OPD tidak dapat didisposisi sama
                // sekali; begitu OPD-nya terdaftar di registry, seed berikutnya
                // menyambungkannya. Yang SUDAH terisi tidak disentuh — bisa
                // jadi admin sengaja memindahkannya lewat panel.
                if ($ada->opd_id === null && $opdId !== null) {
                    $ubah['opd_id'] = $opdId;
                }

                $ada->update($ubah);

                continue;
            }

            Category::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'hint' => $data['hint'] ?? null,
                'icon_name' => $data['icon_name'],
                'color' => $data['color'],
                'opd_id' => $opdId,
                'sla_hours' => $data['sla_hours'],
                'uses_working_days' => false,
                'sort_order' => $urutan,
                'is_active' => true,
                'is_sensitive' => $data['is_sensitive'] ?? false,
            ]);
        }

        $tanpaOpd = Category::whereNull('opd_id')->count();

        if ($tanpaOpd > 0) {
            $this->command?->warn(
                "  {$tanpaOpd} kategori belum punya OPD — disposisi otomatis (#18) tidak dapat berjalan untuk kategori tersebut."
            );
        }
    }

    /**
     * Cocokkan `opd_code` ke registry.
     *
     * Dicocokkan lewat kolom `code`, bukan mencocokkan nama dinas sebagai teks:
     * nama di berkas rapat ditulis panjang ("Dinas Sosial, Pemberdayaan
     * Perempuan dan Perlindungan Anak") dan pencocokan teks akan meleset
     * begitu ada satu koma bergeser.
     */
    protected function resolveOpd(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        return \Nawasara\Registry\Models\Opd::where('code', $code)->value('id');
    }
}
