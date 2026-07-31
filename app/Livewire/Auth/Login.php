<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Satu-satunya pintu masuk aplikasi, untuk semua peran. (§D)
 *
 * Sebelumnya ada dua halaman login terpisah (panel admin & mode kolektor).
 * Pengguna yang salah pintu tetap ditolak walau kata sandinya benar, karena
 * tiap halaman hanya menerima perannya sendiri — membingungkan, terutama bagi
 * kolektor lansia. Sekarang semua masuk lewat sini dan sistem yang menentukan
 * tujuannya berdasarkan peran.
 */
#[Layout('layouts::kolektor')]
#[Title('Masuk')]
class Login extends Component
{
    /**
     * Nama pengguna. Email panjang menyulitkan di keyboard HP, jadi field ini
     * menerima username pendek — email lama tetap diterima agar akun yang
     * terbiasa memakainya tidak terkunci.
     */
    public string $username = '';

    public string $password = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->sendToRoleHome();
        }
    }

    public function login(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], attributes: [
            'username' => 'nama pengguna',
            'password' => 'kata sandi',
        ]);

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->credentials(), remember: true)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Akun ini sedang tidak aktif. Hubungi pengurus.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->sendToRoleHome();
    }

    /**
     * Kirim pengguna ke tempat yang sesuai perannya — inilah inti "satu gate":
     * yang masuk tidak perlu tahu URL mana yang benar untuk dirinya.
     */
    protected function sendToRoleHome(): void
    {
        // Sengaja muat ulang penuh (tanpa wire:navigate): panel admin dan mode
        // kolektor memakai layout serta bundel aset yang berbeda, sehingga
        // pertukaran ala SPA meninggalkan Alpine panel dalam keadaan rusak.
        $this->redirect(
            Auth::user()->isManager()
                ? route('filament.admin.pages.dashboard')
                : route('kolektor.groups'),
        );
    }

    /**
     * Cocokkan sebagai username, atau sebagai email bila yang diketik memuat "@".
     *
     * @return array<string, string>
     */
    protected function credentials(): array
    {
        $identifier = Str::squish($this->username);
        $field = Str::contains($identifier, '@') ? 'email' : 'username';

        return [
            $field => $identifier,
            'password' => $this->password,
        ];
    }

    /**
     * Tanpa ini pintu login terbuka untuk percobaan kata sandi tanpa batas.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), maxAttempts: 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil($seconds / 60).' menit.',
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower(Str::squish($this->username)).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
