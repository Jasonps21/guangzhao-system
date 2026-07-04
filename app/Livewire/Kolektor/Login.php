<?php

namespace App\Livewire\Kolektor;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::kolektor')]
#[Title('Masuk Kolektor')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        $user = Auth::user();

        if (! $user->isCollector() || ! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini bukan kolektor aktif.',
            ]);
        }

        request()->session()->regenerate();

        $this->redirectRoute('kolektor.groups', navigate: true);
    }

    public function render()
    {
        return view('livewire.kolektor.login');
    }
}
