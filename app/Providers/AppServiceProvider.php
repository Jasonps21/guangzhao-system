<?php

namespace App\Providers;

use App\Support\ActivityRecorder;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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

        $this->recordAuthenticationActivity();
    }

    /**
     * Masuk, keluar, dan percobaan masuk yang gagal tidak meninggalkan jejak di
     * tabel mana pun, jadi ketiganya diambil dari event bawaan Laravel.
     *
     * Didaftarkan di sini, bukan sebagai kelas di app/Listeners, supaya tidak
     * menambah folder dasar baru pada struktur aplikasi.
     */
    private function recordAuthenticationActivity(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            app(ActivityRecorder::class)->signedIn($event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            app(ActivityRecorder::class)->signedOut($event->user);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            // Kredensial memuat kata sandi yang diketik; hanya pengenalnya yang
            // boleh ikut tercatat.
            $identifier = $event->credentials['username']
                ?? $event->credentials['email']
                ?? null;

            app(ActivityRecorder::class)->signInFailed(
                is_string($identifier) ? $identifier : null,
            );
        });
    }
}
