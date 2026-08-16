<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Aspirations\Livewire\Category\Index as CategoryIndex;
use Nawasara\Aspirations\Livewire\Dashboard\Index as DashboardIndex;
use Nawasara\Aspirations\Livewire\Report\Index as ReportIndex;
use Nawasara\Aspirations\Livewire\Settings\Index as SettingsIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

/*
|--------------------------------------------------------------------------
| Panel Nawasara — konfigurasi & pemantauan Lapor Bunda
|--------------------------------------------------------------------------
| Bukan panel penanganan laporan. Disposisi, tanggapan petugas, dan verifikasi
| Kabid ada di panel Next.js lewat `api.staff`; halaman di sini hanya untuk
| mengatur kebijakan dan melihat keadaan tanpa berpindah aplikasi.
|
| Digerbang dua kali: middleware permission di rute, dan authorize() di dalam
| komponen. Rute saja tidak cukup — komponen Livewire dapat dipanggil lewat
| permintaan berikutnya tanpa melewati rute yang sama.
*/

Route::middleware(['web', 'auth'])->prefix('nawasara-aspirations')->group(function () {

    Route::get('dashboard', DashboardIndex::class)
        ->middleware(PermissionMiddleware::using('aspirations.dashboard.view'))
        ->name('nawasara-aspirations.dashboard');

    Route::get('reports', ReportIndex::class)
        ->middleware(PermissionMiddleware::using('aspirations.report.view'))
        ->name('nawasara-aspirations.reports');

    Route::get('categories', CategoryIndex::class)
        ->middleware(PermissionMiddleware::using('aspirations.category.view'))
        ->name('nawasara-aspirations.categories');

    Route::get('settings', SettingsIndex::class)
        ->middleware(PermissionMiddleware::using('aspirations.category.manage'))
        ->name('nawasara-aspirations.settings');
});
