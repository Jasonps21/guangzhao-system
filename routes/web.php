<?php

use App\Http\Controllers\PdfController;
use App\Livewire\Auth\Login as LoginGate;
use App\Livewire\Kolektor\Billing;
use App\Livewire\Kolektor\GroupList;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

/*
 * Gerbang masuk tunggal untuk semua peran. (§D)
 *
 * Sebelumnya panel admin dan mode kolektor punya halaman login sendiri-sendiri,
 * sehingga pengguna yang salah pintu ditolak walau kata sandinya benar. URL lama
 * sengaja tetap hidup sebagai pengalihan supaya bookmark & pintasan layar utama
 * milik kolektor tidak mati.
 */
Route::livewire('masuk', LoginGate::class)->name('login');
Route::redirect('admin/login', '/masuk');

/*
 * Mode Kolektor (lapangan) — halaman PWA sederhana, terpisah dari panel admin. (§D, §6.2)
 */
Route::prefix('kolektor')->name('kolektor.')->group(function () {
    Route::get('manifest.webmanifest', [PdfController::class, 'manifest'])->name('manifest');

    Route::redirect('login', '/masuk')->name('login');

    Route::middleware('auth')->group(function () {
        Route::livewire('/', GroupList::class)->name('groups');
        Route::livewire('{group}', Billing::class)->name('billing');
    });
});

/*
 * Cetak PDF — kupon iuran & kartu anggota. (§F, §G)
 *
 * Hanya manajer (Super Admin & Admin). Kolektor sudah login untuk mode lapangan,
 * jadi middleware 'auth' saja tidak cukup untuk menutup akses lewat URL langsung.
 */
Route::middleware(['auth', 'manager'])->group(function () {
    Route::get('cetak/kupon', [PdfController::class, 'coupons'])->name('dues.coupons');
    Route::get('cetak/kupon-pilihan', [PdfController::class, 'selectedCoupons'])->name('dues.coupons.selected');
    Route::get('cetak/anggota', [PdfController::class, 'memberList'])->name('members.list');
    Route::get('cetak/kartu/{member}', [PdfController::class, 'memberCard'])->name('members.card');

    /*
     * Nota cetak: uang duka (tanda terima + tanda pemberian), dana sosial,
     * dan pembayaran iuran beberapa bulan. (§H)
     */
    Route::get('cetak/duka/{payment}/terima', [PdfController::class, 'condolenceReceipt'])->name('condolence.receipt');
    Route::get('cetak/duka/{payment}/pemberian', [PdfController::class, 'condolenceHandover'])->name('condolence.handover');
    Route::get('cetak/bantuan/{record}', [PdfController::class, 'socialFundReceipt'])->name('social-fund.receipt');
    Route::get('cetak/iuran/{receipt}', [PdfController::class, 'duesReceipt'])->name('dues.receipt');
});
