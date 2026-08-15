<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Aspirations\Http\Api\StaffReportController;

/*
|--------------------------------------------------------------------------
| Endpoint PANEL STAF — di belakang JWT realm pegawai (`api.staff`)
|--------------------------------------------------------------------------
| Dipakai panel Next.js. Isolasi per-OPD dikerjakan global scope pada model,
| bukan where-clause di controller.
|
| Rute statis ditaruh SEBELUM yang ber-parameter, supaya
| `reports/verification-queue` tidak tertangkap sebagai `reports/{code}`.
*/

Route::get('/reports/verification-queue',
    [StaffReportController::class, 'verificationQueue'])->name('reports.verification-queue');

Route::get('/reports', [StaffReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{code}', [StaffReportController::class, 'show'])->name('reports.show');

// Aksi alur kerja. POST, bukan PATCH: masing-masing adalah tindakan dengan
// aturannya sendiri, bukan penyuntingan kolom.
Route::post('/reports/{code}/start', [StaffReportController::class, 'startWork'])->name('reports.start');
Route::post('/reports/{code}/submit', [StaffReportController::class, 'submitForVerification'])->name('reports.submit');
Route::post('/reports/{code}/approve', [StaffReportController::class, 'approve'])->name('reports.approve');
Route::post('/reports/{code}/reject', [StaffReportController::class, 'rejectWork'])->name('reports.reject');

Route::post('/reports/{code}/evidence', [StaffReportController::class, 'uploadEvidence'])->name('reports.evidence');
