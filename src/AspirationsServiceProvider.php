<?php

namespace Nawasara\Aspirations;

use Illuminate\Support\ServiceProvider;
use Nawasara\Aspirations\Contracts\GeocodingProvider;
use Nawasara\Aspirations\Jobs\AutoCloseJob;
use Nawasara\Aspirations\Jobs\CheckSlaJob;
use Nawasara\Aspirations\Jobs\CheckVerificationDueJob;
use Nawasara\Aspirations\Services\Geocoding\GoogleGeocoder;
use Nawasara\Aspirations\Services\Geocoding\NullGeocoder;

class AspirationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerGeocoder();

        $this->mergeConfigFrom(
            __DIR__.'/../config/nawasara-aspirations.php',
            'nawasara-aspirations'
        );
    }

    /**
     * Penyedia geocoding, dipilih dari config.
     *
     * Bawaannya NullGeocoder — sistem berjalan penuh tanpa kunci Google.
     * Menukar penyedia (Nominatim, layanan dalam negeri) cukup menambah satu
     * cabang di sini; GeocodeReportJob tidak berubah sama sekali.
     */
    protected function registerGeocoder(): void
    {
        $this->app->bind(GeocodingProvider::class, function () {
            return match (config('nawasara-aspirations.geocoding.provider', 'null')) {
                'google' => new GoogleGeocoder,
                default => new NullGeocoder,
            };
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerCitizenRoutes();
        $this->registerStaffRoutes();
        $this->registerSchedule();

        $this->publishes([
            __DIR__.'/../config/nawasara-aspirations.php' => config_path('nawasara-aspirations.php'),
        ], 'nawasara-aspirations-config');
    }

    /**
     * Penjadwalan job SLA.
     *
     * Memakai `$schedule->call()`, BUKAN `$schedule->command()` — console
     * command yang didaftarkan dari paket tidak selalu muncul di kernel Artisan
     * saat scheduler boot, dan kegagalannya diam-diam (CLAUDE.md §7).
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            if (! config('nawasara-aspirations.scheduler.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

            // Tiap jam: memeriksa DUA rentang sekaligus (tanggapan pertama dan
            // penyelesaian). Sejam cukup rapat untuk SLA berhari-hari, dan
            // tidak membebani basis data.
            //
            // ⚠️ Kategori Bencana berjanji 3 jam. Bila kategori seperti itu
            // dipakai, pemeriksaan per jam berarti eskalasi bisa terlambat
            // sampai 59 menit — perlu ditinjau sebelum kategori berjam aktif.
            $schedule->call(fn () => CheckSlaJob::dispatch())
                ->name('nawasara-aspirations:check-sla')
                ->hourly()
                ->withoutOverlapping(10);

            $schedule->call(fn () => CheckVerificationDueJob::dispatch())
                ->name('nawasara-aspirations:check-verification')
                ->hourly()
                ->withoutOverlapping(10);

            // Harian. WAJIB ber-timezone: app.timezone = UTC, sehingga tanpa
            // ini "jam 2 pagi" jatuh pukul 9 pagi WIB — di tengah jam kerja,
            // saat petugas sedang membuka panelnya.
            $schedule->call(fn () => AutoCloseJob::dispatch())
                ->name('nawasara-aspirations:auto-close')
                ->dailyAt('02:00')
                ->timezone('Asia/Jakarta')
                ->withoutOverlapping(10);
        });
    }

    /**
     * Rute panel staf — di belakang `api.staff` (JWT realm pegawai).
     *
     * Prefix dipisah dari jalur warga (`/staff` vs `/aspirations`) supaya
     * tidak ada endpoint yang tanpa sengaja dapat dicapai dua jenis token.
     * Batas antara "milik warga" dan "milik OPD" harus terlihat dari URL-nya.
     */
    protected function registerStaffRoutes(): void
    {
        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/staff/aspirations';

        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware(['api', 'api.staff'])
            ->name('nawasara-aspirations.staff.')
            ->group(__DIR__.'/../routes/staff.php');
    }

    /**
     * Rute aplikasi warga — di belakang `api.citizen` (JWT realm warga),
     * BUKAN `api.auth`.
     *
     * Prefix mengikuti config nawasara/api supaya seluruh endpoint warga
     * berada di bawah satu awalan yang sama; aplikasi cukup tahu satu alamat
     * dasar.
     *
     * `throttle:nawasara-citizen` memakai limiter yang sudah didaftarkan
     * nawasara/api dan dikunci pada `sub`, bukan IP — puluhan ribu ponsel
     * warga berbagi IP operator, sehingga pembatasan per-IP akan menghukum
     * warga yang tidak melakukan apa-apa.
     */
    protected function registerCitizenRoutes(): void
    {
        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/aspirations';

        \Illuminate\Support\Facades\Route::prefix($prefix)
            ->middleware(['api', 'api.citizen', 'throttle:nawasara-citizen'])
            ->name('nawasara-aspirations.citizen.')
            ->group(__DIR__.'/../routes/citizen.php');
    }
}
