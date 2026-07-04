<?php

namespace App\Livewire\Kolektor;

use App\Enums\CollectionMethod;
use App\Enums\MemberStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\PaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::kolektor')]
#[Title('Daftar Penagihan')]
class Billing extends Component
{
    public MemberGroup $group;

    public int $year;

    public int $month;

    /**
     * Bulan akhir pelunasan yang dipilih kolektor per anggota (member_id => bulan).
     *
     * @var array<int, int>
     */
    public array $payUntil = [];

    public function mount(MemberGroup $group): void
    {
        abort_unless(Auth::user()->groups()->whereKey($group->getKey())->exists(), 403);

        $this->group = $group;
        $now = Carbon::now();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
    }

    public function markPaid(int $duesRecordId): void
    {
        $record = DuesRecord::query()
            ->where('id', $duesRecordId)
            ->forPeriod($this->year, $this->month)
            ->whereHas('member', fn ($q) => $q->where('group_id', $this->group->getKey()))
            ->firstOrFail();

        app(PaymentService::class)->pay($record, Auth::user(), CollectionMethod::Lapangan);
    }

    /**
     * Bayar beberapa bulan ke depan sekaligus. (§6.4)
     *
     * Contoh: tagihan terbit Juni, anggota ingin lunas hingga Desember —
     * kolektor pilih "Desember". Karena lebih dari satu bulan, ini DICATAT
     * SEBAGAI SETORAN MENUNGGU PERSETUJUAN admin (uang harus diverifikasi
     * dulu), bukan langsung lunas. Bila hanya satu bulan, langsung lunas.
     */
    public function payThrough(int $memberId): void
    {
        $member = $this->resolveGroupMember($memberId);
        $toMonth = (int) ($this->payUntil[$memberId] ?? $this->month);
        $toMonth = max($toMonth, $this->month);

        // Satu bulan saja: tidak perlu persetujuan, langsung lunas.
        if ($toMonth === $this->month) {
            $record = $member->duesRecords()->firstOrCreate(
                ['period_year' => $this->year, 'period_month' => $this->month],
                ['amount_due' => $member->monthly_dues_amount, 'status' => \App\Enums\DuesStatus::BelumBayar],
            );

            app(PaymentService::class)->pay($record, Auth::user(), CollectionMethod::Lapangan);
            session()->flash('billing-message', $member->displayName().' ditandai LUNAS untuk '.$this->period());

            return;
        }

        $submission = app(PaymentService::class)->submitRange(
            $member,
            $this->year,
            $this->month,
            $toMonth,
            Auth::user(),
        );

        session()->flash('billing-message', $submission === null
            ? 'Tidak ada bulan yang bisa diajukan (semua sudah lunas / menunggu).'
            : 'Setoran '.$submission->monthsCount().' bulan untuk '.$member->displayName().' dikirim, menunggu persetujuan admin.');
    }

    protected function period(): string
    {
        return Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    /**
     * Pastikan anggota benar-benar milik kelompok yang ditangani kolektor ini.
     */
    protected function resolveGroupMember(int $memberId): Member
    {
        $member = $this->group->members()
            ->where('status', MemberStatus::Aktif)
            ->whereKey($memberId)
            ->first();

        abort_unless($member !== null, 404);

        return $member;
    }

    public function render()
    {
        $records = DuesRecord::query()
            ->with('member')
            ->forPeriod($this->year, $this->month)
            ->whereHas('member', function ($q): void {
                $q->where('group_id', $this->group->getKey())
                    ->where('status', MemberStatus::Aktif);
            })
            ->get()
            ->sortBy(fn (DuesRecord $r) => $r->member->displayName())
            ->values();

        // Default pelunasan: sampai Desember (skenario bayar setahun penuh di muka).
        foreach ($records as $record) {
            $this->payUntil[$record->member_id] ??= 12;
        }

        return view('livewire.kolektor.billing', [
            'records' => $records,
            'period' => Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y'),
            'monthOptions' => $this->monthOptions(),
        ]);
    }

    /**
     * Bulan berjalan hingga Desember sebagai pilihan akhir pelunasan.
     *
     * @return array<int, string>
     */
    protected function monthOptions(): array
    {
        $months = [];
        for ($m = $this->month; $m <= 12; $m++) {
            $months[$m] = Carbon::create($this->year, $m, 1)->translatedFormat('F');
        }

        return $months;
    }
}
