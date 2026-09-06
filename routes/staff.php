<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Aspirations\Http\Api\StaffDashboardController;
use Nawasara\Aspirations\Http\Api\StaffOpdMemberController;
use Nawasara\Aspirations\Http\Api\StaffReportController;
use Nawasara\Aspirations\Http\Api\StaffVerifierController;
use Spatie\Permission\Middleware\PermissionMiddleware;

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

// Pengelolaan verifikator oleh admin OPD sendiri.
//
// Digerbang izin `aspirations.verifier.manage` DAN dibatasi ke OPD pemegangnya
// oleh MembershipResolver — dua lapis, karena izin saja tidak menyebut OPD mana.
Route::middleware(PermissionMiddleware::using('aspirations.verifier.manage'))->group(function () {
    Route::get('/opd/members', [StaffOpdMemberController::class, 'index'])->name('opd.members.index');
    Route::post('/opd/members/{id}/verifier', [StaffOpdMemberController::class, 'assign'])->name('opd.members.verifier.assign');
    Route::delete('/opd/members/{id}/verifier', [StaffOpdMemberController::class, 'revoke'])->name('opd.members.verifier.revoke');
});

// Ringkasan dashboard — satu permintaan, bukan 30.
Route::get('/dashboard/summary', StaffDashboardController::class)->name('dashboard.summary');

// Calon pemeriksa untuk petugas yang sedang masuk.
//
// Menggantikan pemanggilan panel ke `/keycloak/users` yang memakai token
// STATIS di berkas .env — token yang memberi akses seluruh direktori pegawai,
// tidak terikat siapa pun, dan tidak dapat dicabut tanpa mematikan fiturnya
// bagi semua orang.
Route::get('/verifiers', StaffVerifierController::class)->name('verifiers.index');
