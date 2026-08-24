<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Aspirations\Http\Api\PublicMapController;

/*
|--------------------------------------------------------------------------
| Endpoint TERBUKA — tanpa akun sama sekali
|--------------------------------------------------------------------------
|
| ⚠️ Apa pun yang ditambahkan di berkas ini dapat dibaca siapa saja di
| internet, tanpa token, tanpa login. Sebelum menambah rute:
|
|   1. Periksa Resource-nya ditulis sebagai DAFTAR-IZIN, bukan `toArray()`
|      lalu membuang beberapa kolom.
|   2. Pastikan kategori sensitif, laporan bertanda moderasi, nama pelapor,
|      foto, dan koordinat persis TIDAK ikut keluar.
|
| Peta laporan ada di sini karena ia informasi publik yang manfaatnya
| bergantung pada banyaknya mata. Mendukung laporan tidak ada di sini —
| itu tindakan, dan tanpa identitas ia dapat digandakan tanpa batas.
|
| Didaftarkan SEBELUM routes/citizen.php supaya `/reports/map` tidak
| tertangkap lebih dulu oleh `/reports/{code}` di sana.
*/

Route::get('/reports/map', PublicMapController::class)->name('reports.map');
