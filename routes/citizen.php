<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Aspirations\Http\Api\CategoryController;
use Nawasara\Aspirations\Http\Api\CitizenReportController;

/*
|--------------------------------------------------------------------------
| Endpoint WARGA — di belakang JWT Keycloak realm warga
|--------------------------------------------------------------------------
| Dimuat oleh AspirationsServiceProvider dengan middleware `api.citizen`.
| Identitas pelapor selalu dari token, tidak pernah dari badan permintaan.
|
| Kontrak ini dipatok untuk tim Flutter — mengubah nama kunci atau bentuk
| jawaban setelah aplikasi dirilis berarti aplikasi lama rusak, karena yang
| sudah terpasang tidak ikut berubah.
*/

// Kategori. Ditaruh sebelum rute ber-parameter supaya `categories` tidak
// tertangkap sebagai `{code}`.
Route::get('/categories', CategoryController::class)->name('categories');

// Laporan serupa — dipanggil SEBELUM mengirim, agar warga dapat memilih
// "Saya Juga Mengalami" alih-alih menambah laporan ganda.
// Limiter khusus (audit keamanan 15 Oktober 2026, T5) di rute yang membaca
// laporan warga LAIN. Lihat Support\CitizenReadLimits.
Route::get('/reports/similar', [CitizenReportController::class, 'similar'])
    ->middleware('throttle:nawasara-aspirations-similar')
    ->name('reports.similar');

// Linimasa laporan seluruh warga. Juga SEBELUM `/reports/{code}`, supaya
// `feed` tidak tertangkap sebagai kode laporan.
Route::get('/reports/feed', [CitizenReportController::class, 'feed'])->name('reports.feed');

Route::get('/reports', [CitizenReportController::class, 'index'])->name('reports.index');
Route::post('/reports', [CitizenReportController::class, 'store'])->name('reports.store');
Route::get('/reports/{code}', [CitizenReportController::class, 'show'])->name('reports.show');

// Detail laporan warga LAIN, untuk ikut mengawasi. Terpisah dari `show` yang
// hanya untuk pemiliknya; lihat CitizenReportController::showPublic.
Route::get('/reports/{code}/public', [CitizenReportController::class, 'showPublic'])
    ->middleware('throttle:nawasara-aspirations-public_detail')
    ->name('reports.show-public');

Route::post('/reports/{code}/photos', [CitizenReportController::class, 'uploadPhoto'])->name('reports.photos');

// Penilaian & dukungan — menutup lingkaran dari OPD kembali ke warga.
Route::post('/reports/{code}/rate', [CitizenReportController::class, 'rate'])->name('reports.rate');
// Dukungan berbagi penghitung dengan detail publik: jawabannya juga berisi
// laporan warga lain.
Route::post('/reports/{code}/support', [CitizenReportController::class, 'support'])
    ->middleware('throttle:nawasara-aspirations-public_detail')
    ->name('reports.support');
Route::delete('/reports/{code}/support', [CitizenReportController::class, 'unsupport'])
    ->middleware('throttle:nawasara-aspirations-public_detail')
    ->name('reports.unsupport');
