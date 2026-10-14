<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda bahwa peringatan tenggat sudah dikirim ke petugas OPD.
 *
 * Satu kolom per peristiwa, supaya tiap peringatan terkirim TEPAT sekali:
 *
 *   response_warned_at    tenggat tanggapan pertama hampir lewat
 *   resolution_warned_at  tenggat penyelesaian hampir lewat
 *   overdue_notified_at   salah satu tenggat sudah lewat
 *
 * ⚠️ Laporan yang SUDAH terlambat saat migrasi ini berjalan langsung ditandai.
 * Di produksi saat itu 30 dari 34 laporan terbuka sudah lewat tenggat, banyak
 * sejak Agustus. Tanpa penandaan ini, putaran pertama setelah deploy mengirim
 * email tentang seluruh tumpukan itu ke petugas delapan OPD sekaligus, dan
 * peringatan yang datang serentak untuk perkara berminggu-minggu lalu hanya
 * mengajari orang mengabaikannya. Peringatan berlaku untuk keterlambatan BARU.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->timestamp('response_warned_at')->nullable()->after('response_due_at');
            $table->timestamp('resolution_warned_at')->nullable()->after('sla_due_at');
            $table->timestamp('overdue_notified_at')->nullable()->after('resolution_warned_at');
        });

        $now = now();
        $open = ['submitted', 'dispatched', 'in_progress', 'awaiting_verification'];

        // Sudah lewat tenggat tanggapan: tidak perlu peringatan "hampir lewat"
        // maupun "sudah lewat" untuk keterlambatan lama.
        DB::table('nawasara_aspirations_reports')
            ->whereIn('status', $open)
            ->whereNull('first_responded_at')
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<', $now)
            ->update(['response_warned_at' => $now, 'overdue_notified_at' => $now]);

        DB::table('nawasara_aspirations_reports')
            ->whereIn('status', $open)
            ->whereNull('resolution_submitted_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', $now)
            ->update(['resolution_warned_at' => $now, 'overdue_notified_at' => $now]);
    }

    public function down(): void
    {
        Schema::table('nawasara_aspirations_reports', function (Blueprint $table) {
            $table->dropColumn(['response_warned_at', 'resolution_warned_at', 'overdue_notified_at']);
        });
    }
};
