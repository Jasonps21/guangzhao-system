<?php

use App\Http\Controllers\PdfController;
use App\Livewire\Kolektor\Billing;
use App\Livewire\Kolektor\GroupList;
use App\Livewire\Kolektor\Login;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

/*
 * Mode Kolektor (lapangan) — halaman PWA sederhana, terpisah dari panel admin. (§D, §6.2)
 */
Route::prefix('kolektor')->name('kolektor.')->group(function () {
    Route::get('manifest.webmanifest', [PdfController::class, 'manifest'])->name('manifest');

    Route::livewire('login', Login::class)->name('login');

    Route::middleware('auth')->group(function () {
        Route::livewire('/', GroupList::class)->name('groups');
        Route::livewire('{group}', Billing::class)->name('billing');
    });
});

/*
 * Cetak PDF — kupon iuran & kartu anggota. (§F, §G)
 */
Route::middleware('auth')->group(function () {
    Route::get('cetak/kupon', [PdfController::class, 'coupons'])->name('dues.coupons');
    Route::get('cetak/anggota', [PdfController::class, 'memberList'])->name('members.list');
    Route::get('cetak/kartu/{member}', [PdfController::class, 'memberCard'])->name('members.card');
});
