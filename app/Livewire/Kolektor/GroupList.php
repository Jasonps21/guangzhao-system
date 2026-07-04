<?php

namespace App\Livewire\Kolektor;

use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::kolektor')]
#[Title('Pilih Kelompok')]
class GroupList extends Component
{
    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        $this->redirectRoute('kolektor.login', navigate: true);
    }

    public function render()
    {
        $now = Carbon::now();

        $groups = Auth::user()->groups()
            ->withCount(['members as unpaid_count' => function ($query) use ($now): void {
                $query->where('status', MemberStatus::Aktif)
                    ->whereHas('duesRecords', function ($q) use ($now): void {
                        $q->where('period_year', $now->year)
                            ->where('period_month', $now->month)
                            ->where('status', DuesStatus::BelumBayar);
                    });
            }])
            ->orderBy('name')
            ->get();

        return view('livewire.kolektor.group-list', [
            'groups' => $groups,
            'period' => $now->translatedFormat('F Y'),
        ]);
    }
}
