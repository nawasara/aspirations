<?php

namespace Nawasara\Aspirations\Livewire\Settings;

use Livewire\Component;
use Nawasara\Aspirations\Support\Settings;

/**
 * Pengaturan kebijakan Lapor Bunda.
 *
 * Sepuluh angka di halaman ini bukan setelan teknis melainkan JANJI KE WARGA —
 * berapa lama menunggu jawaban, berapa laporan boleh dikirim sehari, seberapa
 * dekat dua laporan disebut sama. Semuanya akan berubah setelah sistem dipakai
 * dan perilaku sebenarnya terlihat, dan mengharuskan deploy untuk mengubahnya
 * berarti perubahan itu tertunda berhari-hari.
 *
 * Halaman ini kecil dan satu-satunya kerjanya adalah formulir, jadi tidak
 * dipecah menjadi komponen anak — pemisahan hanya akan menambah berkas tanpa
 * memisahkan urusan apa pun.
 */
class Index extends Component
{
    /** @var array<string, int> */
    public array $values = [];

    public function mount(): void
    {
        $this->authorize('aspirations.category.manage');

        $this->values = Settings::all();
    }

    /**
     * Batas atas ditetapkan supaya salah ketik tidak menjadi kebijakan.
     * Menyimpan 50000 pada batas laporan per hari sama saja mematikan batasnya,
     * dan tidak ada tanda apa pun bahwa itu terjadi.
     */
    protected function rules(): array
    {
        return [
            'values.reports_per_day' => ['required', 'integer', 'min:1', 'max:100'],
            'values.photos_per_report' => ['required', 'integer', 'min:1', 'max:10'],
            'values.photo_max_kb' => ['required', 'integer', 'min:256', 'max:10240'],
            'values.description_max' => ['required', 'integer', 'min:100', 'max:5000'],
            'values.duplicate_radius' => ['required', 'integer', 'min:10', 'max:1000'],
            'values.duplicate_days' => ['required', 'integer', 'min:1', 'max:90'],
            'values.response_hours' => ['required', 'integer', 'min:1', 'max:2160'],
            'values.verification_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'values.auto_close_days' => ['required', 'integer', 'min:1', 'max:90'],
            'values.reopen_threshold' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    protected function messages(): array
    {
        return [
            'values.*.required' => 'Nilai ini wajib diisi.',
            'values.*.integer' => 'Isi dengan angka bulat.',
            'values.*.min' => 'Nilainya terlalu kecil.',
            'values.*.max' => 'Nilainya terlalu besar.',
        ];
    }

    public function save(): void
    {
        $this->authorize('aspirations.category.manage');

        $this->validate();

        foreach ($this->values as $key => $value) {
            Settings::put($key, (int) $value);
        }

        // Baca ulang dari sumbernya, jangan percaya isi formulir. Settings::int()
        // menolak nilai <= 0 dan jatuh ke config, jadi yang tersimpan bisa saja
        // berbeda dari yang diketik — menampilkan angka yang diketik akan
        // berbohong tentang apa yang sebenarnya berlaku.
        $this->values = Settings::all();

        $this->dispatch('toast', type: 'success', message: 'Pengaturan tersimpan.');
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.settings.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
