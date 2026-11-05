<?php

namespace Nawasara\Aspirations\Tests;

use Carbon\Carbon;
use Nawasara\Aspirations\Jobs\CheckSlaJob;
use Nawasara\Aspirations\Jobs\SendSlaRemindersJob;
use Nawasara\Aspirations\Models\Report;
use Nawasara\Aspirations\Support\SlaReminderPlan;
use PHPUnit\Framework\TestCase;

/**
 * Kapan peringatan tenggat dikirim, dan ke tingkat eskalasi mana laporan naik.
 *
 * Dua-duanya pernah keliru diam-diam: eskalasi melompat ke tingkat 2 begitu
 * tenggat lewat (variabel perulangan menimpa hasilnya), dan peringatan
 * berjendela tetap "24 jam sebelumnya" akan datang sebelum laporan ber-SLA
 * 3 jam sempat masuk.
 */
class SlaReminderTest extends TestCase
{
    private function at(string $time): Carbon
    {
        return Carbon::parse($time, 'UTC');
    }

    public function test_jendela_seperempat_rentang(): void
    {
        // 72 jam: seperempatnya 18 jam.
        $this->assertSame(18.0, SlaReminderPlan::windowHours($this->at('2026-10-01 00:00'), $this->at('2026-10-04 00:00')));
    }

    public function test_jendela_dibatasi_24_jam_untuk_sla_panjang(): void
    {
        // 14 hari: seperempatnya 84 jam, dipangkas ke sehari.
        $this->assertSame(24.0, SlaReminderPlan::windowHours($this->at('2026-10-01 00:00'), $this->at('2026-10-15 00:00')));
    }

    public function test_jendela_minimal_sejam_untuk_sla_pendek(): void
    {
        // 3 jam: seperempatnya 45 menit, lebih sempit dari jarak antar putaran job.
        $this->assertSame(1.0, SlaReminderPlan::windowHours($this->at('2026-10-01 00:00'), $this->at('2026-10-01 03:00')));
    }

    public function test_peringatan_hanya_di_dalam_jendela(): void
    {
        $start = $this->at('2026-10-01 00:00');
        $due = $this->at('2026-10-08 00:00'); // 7 hari, jendela 24 jam

        $this->assertFalse(SlaReminderPlan::shouldWarn($start, $due, false, false, $this->at('2026-10-06 23:00')));
        $this->assertTrue(SlaReminderPlan::shouldWarn($start, $due, false, false, $this->at('2026-10-07 00:00')));
        $this->assertTrue(SlaReminderPlan::shouldWarn($start, $due, false, false, $this->at('2026-10-07 23:30')));
    }

    public function test_tidak_memperingatkan_setelah_lewat_selesai_atau_sudah_diperingatkan(): void
    {
        $start = $this->at('2026-10-01 00:00');
        $due = $this->at('2026-10-08 00:00');
        $inWindow = $this->at('2026-10-07 12:00');

        $this->assertFalse(SlaReminderPlan::shouldWarn($start, $due, false, false, $this->at('2026-10-08 01:00')));
        $this->assertFalse(SlaReminderPlan::shouldWarn($start, $due, true, false, $inWindow));
        $this->assertFalse(SlaReminderPlan::shouldWarn($start, $due, false, true, $inWindow));
        $this->assertFalse(SlaReminderPlan::shouldWarn(null, $due, false, false, $inWindow));
        $this->assertFalse(SlaReminderPlan::shouldWarn($start, null, false, false, $inWindow));
    }

    public function test_sisa_waktu_terbaca(): void
    {
        $now = $this->at('2026-10-01 00:00');

        $this->assertSame('40 menit', SlaReminderPlan::humanRemaining($now, $this->at('2026-10-01 00:40')));
        $this->assertSame('5 jam', SlaReminderPlan::humanRemaining($now, $this->at('2026-10-01 05:10')));
        $this->assertSame('1 hari 3 jam', SlaReminderPlan::humanRemaining($now, $this->at('2026-10-02 03:00')));
        $this->assertSame('2 hari', SlaReminderPlan::humanRemaining($now, $this->at('2026-10-03 00:00')));
    }

    public function test_jendela_mengikuti_config(): void
    {
        $limits = SlaReminderPlan::limitsFrom(['window_share' => 0.5, 'min_window_hours' => 2, 'max_window_hours' => 48]);

        // 72 jam × 0,5 = 36 jam, di dalam 2–48.
        $this->assertSame(36.0, SlaReminderPlan::windowHours($this->at('2026-10-01 00:00'), $this->at('2026-10-04 00:00'), $limits));
    }

    /** Angka config yang keliru tidak boleh mematikan peringatan diam-diam. */
    public function test_config_keliru_dijaga(): void
    {
        $limits = SlaReminderPlan::limitsFrom(['window_share' => 0, 'min_window_hours' => 0.1, 'max_window_hours' => 0]);

        $this->assertSame(0.25, $limits['share'], 'share 0 berarti tidak pernah memperingatkan.');
        $this->assertSame(1.0, $limits['min'], 'Di bawah sejam dapat terlewat jadwal per jam.');
        $this->assertSame(1.0, $limits['max'], 'max tidak boleh di bawah min.');
    }

    private function report(array $attrs): Report
    {
        $report = new Report;
        $report->setRawAttributes(array_merge([
            'code' => 'LB-2026-10-0001',
            'status' => 'in_progress',
            'received_at' => $this->at('2026-10-01 00:00'),
            'response_due_at' => $this->at('2026-10-04 00:00'),  // 72 jam, jendela 18 jam
            'sla_due_at' => $this->at('2026-10-08 00:00'),        // 7 hari, jendela 24 jam
            'first_responded_at' => null,
            'resolution_submitted_at' => null,
            'response_warned_at' => null,
            'resolution_warned_at' => null,
            'overdue_notified_at' => null,
        ], $attrs));

        return $report;
    }

    public function test_peristiwa_terdeteksi_di_jendelanya(): void
    {
        $job = new SendSlaRemindersJob(dryRun: true);

        $this->assertSame([], $job->eventsFor($this->report([]), $this->at('2026-10-02 00:00')));
        $this->assertSame([SendSlaRemindersJob::KIND_RESPONSE], $job->eventsFor($this->report([]), $this->at('2026-10-03 12:00')));
        $this->assertSame([SendSlaRemindersJob::KIND_OVERDUE], $job->eventsFor($this->report(['first_responded_at' => null]), $this->at('2026-10-04 01:00')));
    }

    /**
     * Inti idempotensi: setelah penanda terisi, putaran berikutnya diam.
     *
     * Kuncinya stabil per (laporan, jenis tenggat) — kolom di baris laporan —
     * bukan per putaran job. Peringatan yang berulang tiap jam mengajari
     * petugas mengabaikannya.
     */
    public function test_tidak_mengirim_dua_kali_setelah_ditandai(): void
    {
        $job = new SendSlaRemindersJob(dryRun: true);
        $now = $this->at('2026-10-03 12:00');

        $this->assertSame([], $job->eventsFor($this->report(['response_warned_at' => $now]), $now->copy()->addHour()));

        $overdueAt = $this->at('2026-10-04 01:00');
        $this->assertSame([], $job->eventsFor($this->report([
            'response_warned_at' => $now,
            'overdue_notified_at' => $overdueAt,
        ]), $overdueAt->copy()->addHours(5)));
    }

    /** Satu penanda "sudah lewat" untuk kedua tenggat: laporan yang telat dua kali tetap satu pesan. */
    public function test_lewat_dua_tenggat_tetap_satu_pemberitahuan(): void
    {
        $job = new SendSlaRemindersJob(dryRun: true);
        $events = $job->eventsFor($this->report(['response_warned_at' => $this->at('2026-10-03 12:00'), 'resolution_warned_at' => $this->at('2026-10-07 00:00')]), $this->at('2026-10-09 00:00'));

        $this->assertSame([SendSlaRemindersJob::KIND_OVERDUE], $events);
    }

    public function test_pemeriksa_hanya_menerima_yang_sudah_lewat(): void
    {
        $items = [
            ['report' => $this->report([]), 'kind' => SendSlaRemindersJob::KIND_RESPONSE],
            ['report' => $this->report([]), 'kind' => SendSlaRemindersJob::KIND_OVERDUE],
        ];

        $this->assertCount(2, SendSlaRemindersJob::itemsForRecipient($items, true, true));
        $this->assertSame([SendSlaRemindersJob::KIND_OVERDUE], array_column(SendSlaRemindersJob::itemsForRecipient($items, false, true), 'kind'));
        $this->assertSame([], SendSlaRemindersJob::itemsForRecipient($items, false, false));
    }

    public function test_kanal_keliru_kembali_ke_email(): void
    {
        $this->assertSame(['email'], SendSlaRemindersJob::channels(null));
        $this->assertSame(['email'], SendSlaRemindersJob::channels(['sms']));
        $this->assertSame(['email', 'telegram'], SendSlaRemindersJob::channels(['telegram', 'email']));
    }

    public function test_eskalasi_bertahap_bukan_langsung_tingkat_dua(): void
    {
        $this->assertSame(1, CheckSlaJob::levelFor(0, 0));
        $this->assertSame(1, CheckSlaJob::levelFor(47.9, 0));
        $this->assertSame(2, CheckSlaJob::levelFor(48, 0));
        $this->assertSame(2, CheckSlaJob::levelFor(48, 1));
    }

    public function test_belum_lewat_tidak_eskalasi_dan_tingkat_tidak_turun(): void
    {
        $this->assertSame(0, CheckSlaJob::levelFor(-5, 0));
        $this->assertSame(2, CheckSlaJob::levelFor(1, 2));
    }
}
