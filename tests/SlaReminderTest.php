<?php

namespace Nawasara\Aspirations\Tests;

use Carbon\Carbon;
use Nawasara\Aspirations\Jobs\CheckSlaJob;
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
