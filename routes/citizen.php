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
Route::get('/reports/similar', [CitizenReportController::class, 'similar'])->name('reports.similar');

Route::get('/reports', [CitizenReportController::class, 'index'])->name('reports.index');
Route::post('/reports', [CitizenReportController::class, 'store'])->name('reports.store');
Route::get('/reports/{code}', [CitizenReportController::class, 'show'])->name('reports.show');

Route::post('/reports/{code}/photos', [CitizenReportController::class, 'uploadPhoto'])->name('reports.photos');

// Penilaian & dukungan — menutup lingkaran dari OPD kembali ke warga.
Route::post('/reports/{code}/rate', [CitizenReportController::class, 'rate'])->name('reports.rate');
Route::post('/reports/{code}/support', [CitizenReportController::class, 'support'])->name('reports.support');
Route::delete('/reports/{code}/support', [CitizenReportController::class, 'unsupport'])->name('reports.unsupport');
