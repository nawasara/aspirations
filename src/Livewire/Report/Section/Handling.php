<?php

declare(strict_types=1);

namespace Nawasara\Aspirations\Livewire\Report\Section;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Aspirations\Exceptions\WorkflowException;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Services\ReportWorkflow;
use Nawasara\Registry\Support\MembershipResolver;

/**
 * Penanganan laporan — kerjakan, serahkan ke pemeriksa, setujui, kembalikan.
 *
 * Logikanya sudah lama ada di ReportWorkflow dan dipakai API; yang belum ada
 * adalah tombolnya. Component ini hanya menghubungkan keduanya — seluruh
 * aturan tetap diputuskan workflow, bukan di sini, supaya panel dan aplikasi
 * tidak pernah berbeda pendapat tentang apa yang boleh.
 *
 * ⚠️ **Siapa pun yang ditunjuk memverifikasi DIANGGAP Kabid.** Tidak ada data
 * jabatan, dan itu disengaja: daftar Kabid akan basi tiap mutasi, lalu
 * laporan tertahan karena orang yang terdaftar sudah pindah. Yang menjaga
 * pemisahan pekerja dan pemeriksa adalah aturan di
 * ReportWorkflow::assertVerifierUsable() — terutama larangan menunjuk diri
 * sendiri, yang mutlak.
 */
class Handling extends Component
{
    public Report $report;

    public string $body = '';

    public ?int $verifierId = null;

    public string $rejectReason = '';

    public function mount(Report $report): void
    {
        $this->report = $report;
    }

    /**
     * Calon pemeriksa: se-OPD, bukan diri sendiri.
     *
     * Mengikuti aturan #25 yang sama dengan workflow — pengguna yang belum
     * tertaut OPD mana pun ikut ditawarkan, karena aturan itu memang
     * melonggar selama data keanggotaan belum lengkap. Daftar ini hanya
     * saran; yang menolak tetap server.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function verifierOptions(): array
    {
        $user = auth()->user();
        $userId = (int) $user->getAuthIdentifier();

        $opdId = app(MembershipResolver::class)->opdIdFor($user);

        $seOpd = $opdId === null
            ? collect()
            : DB::table('nawasara_registry_memberships')
                ->where('opd_id', $opdId)
                ->where('user_id', '!=', $userId)
                ->distinct()
                ->pluck('user_id');

        // Pengguna tanpa keanggotaan — sah menurut aturan #25 selama data
        // registry belum lengkap.
        $tanpaOpd = DB::table('users')
            ->whereNotIn('id', DB::table('nawasara_registry_memberships')->pluck('user_id'))
            ->where('id', '!=', $userId)
            ->pluck('id');

        $ids = $seOpd->merge($tanpaOpd)->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Tidak ada satu pun calon pemeriksa.
     *
     * Terjadi saat OPD baru punya SATU pengguna terdaftar — dan itu keadaan
     * yang sekarang berlaku di enam dari tujuh OPD. Larangan menunjuk diri
     * sendiri bersifat mutlak, jadi laporan tidak dapat diserahkan sampai
     * ada anggota kedua.
     *
     * Yang berbahaya bukan buntunya, melainkan buntu yang DIAM: tombol mati
     * tanpa sebab akan dilaporkan sebagai aplikasi rusak. Panel menyebut
     * sebabnya dan ke mana harus pergi.
     */
    public function hasNoVerifier(): bool
    {
        return $this->verifierOptions() === [];
    }

    public function startWork(): void
    {
        $this->authorize('aspirations.report.respond');

        $this->validate(['body' => ['required', 'string', 'min:5', 'max:2000']]);

        $this->run(fn (ReportWorkflow $w) => $w->startWork(
            $this->report, auth()->user(), $this->body,
        ));
    }

    public function submitForVerification(): void
    {
        $this->authorize('aspirations.report.respond');

        $this->validate([
            'verifierId' => ['required', 'integer'],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], [
            'verifierId' => 'Pemeriksa',
            'body' => 'Uraian pekerjaan',
        ]);

        $model = config('auth.providers.users.model');
        $verifier = $model::find($this->verifierId);

        if (! $verifier) {
            $this->addError('verifierId', 'Pengguna yang dipilih tidak ditemukan.');

            return;
        }

        $this->run(fn (ReportWorkflow $w) => $w->submitForVerification(
            $this->report, auth()->user(), $verifier, $this->body,
        ));
    }

    public function approve(): void
    {
        $this->authorize('aspirations.report.verify');

        $this->run(fn (ReportWorkflow $w) => $w->approve($this->report, auth()->user()));
    }

    public function rejectWork(): void
    {
        $this->authorize('aspirations.report.verify');

        $this->validate([
            'rejectReason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'rejectReason' => 'Alasan pengembalian',
        ]);

        $this->run(fn (ReportWorkflow $w) => $w->rejectWork(
            $this->report, auth()->user(), $this->rejectReason,
        ));
    }

    /**
     * Jalankan satu aksi workflow, terjemahkan penolakannya jadi pesan.
     *
     * WorkflowException memuat kalimat yang memang ditulis untuk dibaca staf
     * ("Anda tidak dapat menunjuk diri sendiri..."), jadi diteruskan apa
     * adanya alih-alih diganti pesan umum yang tidak menolong.
     */
    protected function run(callable $action): void
    {
        try {
            $action(app(ReportWorkflow::class));
        } catch (WorkflowException $e) {
            $this->dispatch('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->report->refresh();
        $this->reset(['body', 'verifierId', 'rejectReason']);
        unset($this->verifierOptions);

        $this->dispatch('report-updated');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Laporan diperbarui.']);
    }

    /** Apakah pengguna ini yang ditunjuk memeriksa laporan tersebut. */
    public function isVerifier(): bool
    {
        return $this->report->verifier_id !== null
            && (int) $this->report->verifier_id === (int) auth()->id();
    }

    public function render()
    {
        return view('nawasara-aspirations::livewire.pages.report.section.handling');
    }
}
