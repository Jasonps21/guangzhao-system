<?php

namespace App\Livewire\Kolektor;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use App\Enums\VisitOutcome;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\PaymentService;
use App\Support\VisitService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts::kolektor')]
#[Title('Daftar Penagihan')]
class Billing extends Component
{
    use WithFileUploads;

    public MemberGroup $group;

    public int $year;

    public int $month;

    /**
     * Bulan akhir pelunasan yang dipilih kolektor per anggota (member_id => bulan).
     *
     * @var array<int, int>
     */
    public array $payUntil = [];

    /**
     * Anggota yang panel kunjungannya sedang dibuka (null = tertutup).
     */
    public ?int $activeMemberId = null;

    public ?float $lat = null;

    public ?float $lng = null;

    public ?string $houseAddress = null;

    public string $outcome = VisitOutcome::Bertemu->value;

    public ?string $note = null;

    /**
     * Foto rumah yang diunggah dari kamera HP.
     *
     * @var TemporaryUploadedFile|null
     */
    public $photo = null;

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
            ->with('member')
            ->where('id', $duesRecordId)
            ->forPeriod($this->year, $this->month)
            ->whereHas('member', fn ($q) => $q->where('group_id', $this->group->getKey()))
            ->firstOrFail();

        // Kebijakan "wajib sekali": titik & foto rumah harus ada sebelum penagihan pertama.
        if (! $record->member->hasCompleteLocation()) {
            $this->requireLocation((int) $record->member_id);

            return;
        }

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

        if (! $member->hasCompleteLocation()) {
            $this->requireLocation($memberId);

            return;
        }

        $toMonth = (int) ($this->payUntil[$memberId] ?? $this->month);
        $toMonth = max($toMonth, $this->month);

        // Satu bulan saja: tidak perlu persetujuan, langsung lunas.
        if ($toMonth === $this->month) {
            $record = $member->duesRecords()->firstOrCreate(
                ['period_year' => $this->year, 'period_month' => $this->month],
                ['amount_due' => $member->monthly_dues_amount, 'status' => DuesStatus::BelumBayar],
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

    /**
     * Buka panel kunjungan untuk satu anggota (isi alamat awal dari data terkini).
     */
    public function openVisit(int $memberId): void
    {
        $member = $this->resolveGroupMember($memberId);

        $this->activeMemberId = $memberId;
        $this->houseAddress = $member->address;
        $this->outcome = VisitOutcome::Bertemu->value;
        $this->note = null;
        $this->lat = null;
        $this->lng = null;
        $this->photo = null;
        $this->resetErrorBag();
    }

    public function closeVisit(): void
    {
        $this->activeMemberId = null;
        $this->photo = null;
        $this->resetErrorBag();
    }

    /**
     * Simpan kunjungan: titik GPS + foto rumah + konfirmasi alamat. Untuk anggota
     * yang belum lengkap (kunjungan pertama), ketiganya wajib.
     */
    public function saveVisit(): void
    {
        $member = $this->resolveGroupMember((int) $this->activeMemberId);
        $mustComplete = ! $member->hasCompleteLocation();

        $this->validate([
            'lat' => [$mustComplete ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'lng' => [$mustComplete ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'photo' => [$mustComplete ? 'required' : 'nullable', 'image', 'max:8192'],
            'houseAddress' => ['nullable', 'string', 'max:1000'],
            'outcome' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'lat' => 'titik GPS',
            'lng' => 'titik GPS',
            'photo' => 'foto rumah',
        ]);

        app(VisitService::class)->record($member, Auth::user(), [
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'address' => $this->houseAddress,
            'outcome' => $this->outcome,
            'note' => $this->note,
        ], $this->photo);

        session()->flash('billing-message', 'Data lokasi '.$member->displayName().' tersimpan.');
        $this->closeVisit();
    }

    /**
     * Konfirmasi cepat "lokasi masih sama" tanpa mengambil ulang titik/foto —
     * mencatat kunjungan konfirmasi & memperbarui waktu terakhir diverifikasi.
     */
    public function confirmLocation(int $memberId): void
    {
        $member = $this->resolveGroupMember($memberId);

        app(VisitService::class)->record($member, Auth::user(), [
            'outcome' => VisitOutcome::Bertemu->value,
            'note' => 'Konfirmasi lokasi masih sama.',
        ]);

        session()->flash('billing-message', 'Lokasi '.$member->displayName().' dikonfirmasi masih sama.');
    }

    /**
     * Buka panel kunjungan + beri tahu kolektor bahwa lokasi wajib dilengkapi
     * sebelum bisa menandai pembayaran.
     */
    protected function requireLocation(int $memberId): void
    {
        $this->openVisit($memberId);
        session()->flash('billing-message', 'Lengkapi titik GPS & foto rumah dulu sebelum menandai pembayaran.');
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

        $activeMember = $this->activeMemberId !== null
            ? $records->firstWhere('member_id', $this->activeMemberId)?->member
            : null;

        return view('livewire.kolektor.billing', [
            'records' => $records,
            'activeMember' => $activeMember,
            'period' => Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y'),
            'monthOptions' => $this->monthOptions(),
            'outcomeOptions' => VisitOutcome::cases(),
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
