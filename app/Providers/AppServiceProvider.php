<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Panel admin ikut dibuka dari HP, bukan hanya dari laptop. Tabel lebar
         * memaksa pengurus menggeser ke samping terus-menerus untuk membaca satu
         * baris, jadi di bawah 640px tiap baris ditampilkan sebagai kartu
         * bertumpuk dengan label kolom di atas nilainya.
         *
         * Dipasang di sini, bukan satu per satu di tiap tabel: pengaturannya
         * berlaku juga untuk relation manager, widget laporan, dan tabel apa pun
         * yang dibuat kemudian — tanpa perlu diingat lagi.
         */
        Table::configureUsing(fn (Table $table): Table => $table->stackedOnMobile());
    }
}
