<?php

namespace Nawasara\Aspirations\Services;

use Illuminate\Support\Facades\Log;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Citizen\Models\CitizenDevice;
use Nawasara\Citizen\Models\CitizenProfile;
use Nawasara\Notification\Facades\Notify;

/**
 * Memberi tahu warga saat laporannya bergerak.
 *
 * Tanpa ini warga harus membuka aplikasi berulang kali untuk tahu ada
 * perkembangan — dan kebanyakan tidak akan melakukannya. Laporan yang
 * ditanggapi tetapi tidak diketahui pelapornya sama saja dengan tidak
 * ditanggapi: janji SLA-nya terpenuhi di basis data, tidak di pengalaman warga.
 *
 * ── Kanal ────────────────────────────────────────────────────────────────
 *
 * Push FCM ke semua perangkat pelapor, lalu email. Push hanya dicoba setelah
 * Firebase diisi di Vault (grup `fcm`); sebelum itu hanya email yang berjalan.
 * Pemanggil di ReportWorkflow tidak perlu tahu kanal mana yang aktif.
 *
 * ⚠️ Kegagalan mengirim TIDAK PERNAH menggagalkan perpindahan status. Petugas
 * yang sudah menyelesaikan pekerjaannya tidak boleh melihat galat hanya karena
 * SMTP sedang mati — laporannya tetap selesai, notifikasinya yang gagal.
 */
class ReportNotifier
{
    /**
     * Peristiwa yang layak diberitahukan.
     *
     * Sengaja TIDAK semua perpindahan. `submitted` → `dispatched` terjadi
     * seketika dan otomatis; memberitahukannya berarti warga menerima dua
     * pesan dalam hitungan detik setelah mengirim, dan pesan yang terlalu
     * sering membuat orang mematikan notifikasi — lalu yang penting pun ikut
     * hilang.
     */
    protected const NOTIFIABLE = [
        Report::STATUS_IN_PROGRESS => 'aspirations.report.in_progress',
        Report::STATUS_RESOLVED => 'aspirations.report.resolved',
        Report::STATUS_REJECTED => 'aspirations.report.rejected',
    ];

    /**
     * Kirim pemberitahuan perubahan status.
     *
     * Dipanggil dari ReportWorkflow setelah perpindahan tersimpan — bukan
     * sebelumnya, supaya tidak ada pesan terkirim untuk perubahan yang
     * ternyata gagal.
     */
    public function statusChanged(Report $report, string $newStatus): void
    {
        $template = self::NOTIFIABLE[$newStatus] ?? null;

        if ($template === null) {
            return;
        }

        $this->dispatchNotification($report, $template, [
            'subject' => $this->subjectFor($newStatus),
            'body' => $this->bodyFor($report, $newStatus),
        ]);
    }

    /** Laporan dibuka kembali karena penilaian rendah (#6). */
    public function reopened(Report $report): void
    {
        $this->dispatchNotification($report, 'aspirations.report.reopened', [
            'subject' => 'Laporan Anda dibuka kembali',
            'body' => "Terima kasih atas penilaian Anda. Laporan {$report->code} kami buka kembali untuk ditangani ulang.",
        ]);
    }

    /**
     * Kirim ke pelapor.
     *
     * ⚠️ Dikirim ke warga meski laporannya ANONIM. "Anonim" menyembunyikan nama
     * dari petugas OPD, bukan memutus hubungan sistem dengan pelapornya —
     * `keycloak_sub` tetap tersimpan justru supaya hal seperti ini tetap bisa.
     * Warga yang melapor anonim tetap berhak tahu laporannya ditangani.
     */
    protected function dispatchNotification(Report $report, string $template, array $data): void
    {
        $context = [
            'report_code' => $report->code,
            'status' => $report->status,
            // Kunci template disertakan supaya kelak dapat diganti
            // menjadi template tersimpan tanpa mengubah pemanggil.
            'template' => $template,
        ];

        // Push dan email dikirim TERPISAH, masing-masing dengan penjaganya
        // sendiri: Firebase yang belum dikonfigurasi tidak boleh menahan
        // email, dan sebaliknya.
        $this->sendPush($report, $data, $context);
        $this->sendEmail($report, $data, $context);
    }

    /**
     * Push ke SEMUA perangkat pelapor (ponsel dan tablet), lebih dulu dari
     * email: aplikasi adalah tempat warga membaca laporannya, dan surel
     * pemerintah sering tidak dibuka.
     *
     * `push_data` dibaca aplikasi untuk membuka laporan yang dimaksud saat
     * notifikasinya diketuk. `push_channel` harus sama dengan id kanal
     * notifikasi yang dibuat aplikasi.
     */
    protected function sendPush(Report $report, array $data, array $context): void
    {
        try {
            if (! $this->pushReady()) {
                return;
            }

            $tokens = CitizenDevice::tokensFor($report->keycloak_sub);
            if ($tokens->isEmpty()) {
                return;
            }

            Notify::to(...$tokens->all())
                ->channel(['fcm'])
                ->subject($data['subject'])
                ->body($data['body'])
                ->context($context + [
                    'push_channel' => self::PUSH_CHANNEL,
                    'push_data' => [
                        'type' => 'report',
                        'code' => $report->code,
                        'status' => $report->status,
                    ],
                ])
                ->send();
        } catch (\Throwable $e) {
            // Ditelan dengan sengaja; lihat catatan kelas.
            Log::warning('aspirations: push gagal dikirim', [
                'report' => $report->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendEmail(Report $report, array $data, array $context): void
    {
        try {
            $email = $this->reporterEmail($report);

            if ($email === null) {
                // Bukan galat: warga bisa saja mendaftar lewat Google tanpa
                // email tersimpan, atau profilnya belum terbentuk. Dicatat
                // supaya terlihat bila ternyata sering terjadi.
                Log::info('aspirations: pelapor tanpa email, notifikasi dilewati', [
                    'report' => $report->code,
                ]);

                return;
            }

            Notify::to($email)
                ->channel(['email'])
                ->subject($data['subject'])
                ->body($data['body'])
                ->context($context)
                ->send();
        } catch (\Throwable $e) {
            // Ditelan dengan sengaja; lihat catatan kelas.
            Log::warning('aspirations: notifikasi gagal dikirim', [
                'report' => $report->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Id kanal notifikasi Android yang dibuat aplikasi untuk kabar laporan.
     * Disepakati dengan tim aplikasi; lihat docs/api-superapps/notifikasi.md.
     */
    public const PUSH_CHANNEL = 'laporan';

    /**
     * Push hanya dicoba bila kanal FCM ada DAN Firebase sudah diisi di Vault.
     *
     * Tanpa penjaga ini, setiap perubahan status selama Firebase belum
     * disiapkan akan meninggalkan baris log gagal untuk tiap perangkat,
     * setelah tiga kali percobaan, dan menenggelamkan kegagalan yang nyata.
     */
    protected function pushReady(): bool
    {
        try {
            return app(\Nawasara\Notification\Services\NotificationService::class)
                ->driver('fcm')
                ->isReady();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Surel pelapor, dari profil warga. */
    protected function reporterEmail(Report $report): ?string
    {
        return CitizenProfile::where('keycloak_sub', $report->keycloak_sub)
            ->value('email');
    }

    protected function subjectFor(string $status): string
    {
        return match ($status) {
            Report::STATUS_IN_PROGRESS => 'Laporan Anda sedang ditangani',
            Report::STATUS_RESOLVED => 'Laporan Anda telah selesai',
            Report::STATUS_REJECTED => 'Laporan Anda belum dapat diproses',
            default => 'Ada perkembangan pada laporan Anda',
        };
    }

    /**
     * Isi pesan.
     *
     * Menyebut kode laporan dan dinas yang menangani — dua hal yang membuat
     * pesan terasa ditujukan kepada orangnya, bukan surat massal. Warga yang
     * mengirim beberapa laporan harus dapat langsung tahu yang mana.
     */
    protected function bodyFor(Report $report, string $status): string
    {
        $opd = $report->opd?->name ?? 'perangkat daerah terkait';

        return match ($status) {
            Report::STATUS_IN_PROGRESS => "Laporan {$report->code} sedang ditangani oleh {$opd}.",

            Report::STATUS_RESOLVED => "Laporan {$report->code} telah dinyatakan selesai oleh {$opd}. "
                .'Mohon beri penilaian agar kami tahu hasilnya sesuai harapan Anda.',

            Report::STATUS_REJECTED => "Laporan {$report->code} belum dapat diproses. "
                .'Silakan buka aplikasi untuk membaca alasannya.',

            default => "Ada perkembangan pada laporan {$report->code}.",
        };
    }
}
