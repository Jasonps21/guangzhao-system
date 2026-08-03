<?php

namespace App\Livewire\Kolektor;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\MemberStatus;
use App\Enums\VisitOutcome;
use App\Enums\WhatsappOwner;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\PaymentService;
use App\Support\PhoneNumber;
use App\Support\VisitService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
     * Kata kunci pencarian anggota: nama, nomor anggota, alamat, atau telepon.
     * Kolektor di depan pintu tidak boleh perlu menggulir 194 kartu satu per satu.
     */
    public string $search = '';

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

    /**
     * Nomor anggota itu sendiri — HP atau telepon rumah.
     */
    public ?string $phone = null;

    /**
     * Nomor yang bisa dihubungi lewat WhatsApp. Sering bukan milik anggota:
     * banyak anggota lansia memakai telepon rumah, dan yang memegang
     * WhatsApp adalah anak atau menantunya.
     */
    public ?string $whatsappPhone = null;

    public string $whatsappOwner = WhatsappOwner::Sendiri->value;

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

        if (app(PaymentService::class)->pay($record, Auth::user(), CollectionMethod::Lapangan)) {
            session()->flash('billing-message', $record->member->displayName().' ditandai LUNAS untuk '.$this->period().'. Salah tekan? Tekan "Batalkan pembayaran" pada baris itu.');
        }
    }

    /**
     * Batalkan pembayaran yang baru saja salah ditandai kolektor sendiri,
     * selama masih dalam tenggang. Lewat itu, koreksi harus lewat admin.
     */
    public function undoPayment(int $duesRecordId): void
    {
        $record = DuesRecord::query()
            ->with('member')
            ->where('id', $duesRecordId)
            ->forPeriod($this->year, $this->month)
            ->whereHas('member', fn ($q) => $q->where('group_id', $this->group->getKey()))
            ->firstOrFail();

        session()->flash('billing-message', app(PaymentService::class)->undoFieldPayment($record, Auth::user())
            ? 'Pembayaran '.$record->member->displayName().' dibatalkan. Tagihan kembali BELUM BAYAR.'
            : 'Pembayaran ini sudah tidak bisa dibatalkan sendiri. Hubungi admin untuk mengoreksi.');
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
            session()->flash('billing-message', $member->displayName().' ditandai LUNAS untuk '.$this->period().'. Salah tekan? Tekan "Batalkan pembayaran" pada baris itu.');

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
        // Sebagian anggota menyimpan dua nomor sekaligus di kolom ini. Nilai
        // seperti itu tidak akan lolos validasi, jadi jangan diisikan —
        // kolektor akan terjebak pada anggota yang justru sudah punya nomor.
        // Nomor lamanya tetap ditampilkan sebagai keterangan.
        $this->phone = PhoneNumber::isValid($member->phone)
            ? PhoneNumber::normalize($member->phone)
            : null;
        $this->whatsappPhone = PhoneNumber::isValid($member->whatsapp_phone)
            ? PhoneNumber::normalize($member->whatsapp_phone)
            : null;
        $this->whatsappOwner = ($member->whatsapp_owner ?? WhatsappOwner::Sendiri)->value;
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
        $this->phone = null;
        $this->whatsappPhone = null;
        $this->whatsappOwner = WhatsappOwner::Sendiri->value;
        $this->resetErrorBag();
    }

    /**
     * Kasus paling umum: nomor anggota itu sendiri juga nomor WhatsApp-nya.
     * Satu ketukan, supaya kolektor tidak mengetik nomor yang sama dua kali.
     */
    public function copyPhoneToWhatsapp(): void
    {
        $this->whatsappPhone = $this->phone;
        $this->whatsappOwner = WhatsappOwner::Sendiri->value;
    }

    /**
     * Simpan kunjungan: titik GPS + foto rumah + konfirmasi alamat. Untuk anggota
     * yang belum lengkap (kunjungan pertama), ketiganya wajib.
     */
    public function saveVisit(): void
    {
        $member = $this->resolveGroupMember((int) $this->activeMemberId);
        $mustComplete = ! $member->hasCompleteLocation();
        $this->normalizeContactInput();

        $this->validate([
            'lat' => [$mustComplete ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'lng' => [$mustComplete ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'photo' => [$mustComplete ? 'required' : 'nullable', 'image', 'max:8192'],
            ...$this->contactRules(),
            'houseAddress' => ['nullable', 'string', 'max:1000'],
            'outcome' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'lat' => 'titik GPS',
            'lng' => 'titik GPS',
            'photo' => 'foto rumah',
        ]);

        $this->assertReachable();

        app(VisitService::class)->record($member, Auth::user(), [
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'address' => $this->houseAddress,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsappPhone,
            'whatsapp_owner' => $this->whatsappOwner,
            'outcome' => $this->outcome,
            'note' => $this->note,
        ], $this->photo);

        session()->flash('billing-message', 'Data lokasi '.$member->displayName().' tersimpan.');
        $this->closeVisit();
    }

    /**
     * Jalan keluar ketika foto gagal terkirim (sinyal putus di lapangan):
     * simpan titik GPS & alamatnya saja supaya perjalanan kolektor ke rumah itu
     * tidak terbuang, lalu fotonya menyusul pada kunjungan berikutnya.
     *
     * Kebijakan "wajib sekali" tidak dilonggarkan: selama fotonya belum ada,
     * hasCompleteLocation() tetap false sehingga anggota belum bisa ditandai lunas.
     */
    public function saveLocationOnly(): void
    {
        $member = $this->resolveGroupMember((int) $this->activeMemberId);
        $this->normalizeContactInput();

        $this->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            ...$this->contactRules(),
            'houseAddress' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'lat' => 'titik GPS',
            'lng' => 'titik GPS',
        ]);

        $this->assertReachable();

        app(VisitService::class)->record($member, Auth::user(), [
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'address' => $this->houseAddress,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsappPhone,
            'whatsapp_owner' => $this->whatsappOwner,
            'outcome' => $this->outcome,
            'note' => $this->note,
        ]);

        session()->flash('billing-message', 'Titik lokasi '.$member->displayName().' tersimpan. Foto rumah masih perlu diambil sebelum anggota ini bisa ditandai lunas.');
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
     * Rapikan kedua nomor sebelum divalidasi, supaya kolektor yang salah ketik
     * melihat bentuk bersihnya, bukan ketikan mentahnya.
     */
    protected function normalizeContactInput(): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);
        $this->whatsappPhone = PhoneNumber::normalize($this->whatsappPhone);
    }

    /**
     * Aturan kedua kolom nomor, dipakai kedua jalur simpan. Bentuk nomor yang
     * sah ditentukan satu tempat saja, di PhoneNumber.
     *
     * Anggota harus bisa dihubungi lewat salah satu dari keduanya — tidak
     * dipaksa dua-duanya, karena ada anggota yang seisi keluarganya tidak
     * memakai WhatsApp, dan ada yang nomornya sendiri sudah cukup.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function contactRules(): array
    {
        $single = fn (string $label): Closure => function (string $attribute, mixed $value, Closure $fail) use ($label): void {
            if (blank($value)) {
                return;
            }

            if (! PhoneNumber::isValid(is_string($value) ? $value : null)) {
                $fail('Isi satu '.$label.' saja — mis. 081234567890 atau 0411362589.');
            }
        };

        return [
            'phone' => ['nullable', 'string', $single('nomor telepon')],
            'whatsappPhone' => ['nullable', 'string', $single('nomor WhatsApp')],
            'whatsappOwner' => ['required', Rule::enum(WhatsappOwner::class)],
        ];
    }

    /**
     * Anggota harus bisa dihubungi lewat salah satu nomor. Diperiksa terpisah
     * dari aturan di atas: Laravel melewati closure pada kolom `nullable` yang
     * kosong, padahal justru keadaan kosong itulah yang ingin ditangkap.
     */
    protected function assertReachable(): void
    {
        if (filled($this->phone) || filled($this->whatsappPhone)) {
            return;
        }

        throw ValidationException::withMessages([
            'phone' => 'Isi nomor telepon atau nomor WhatsApp — minimal salah satu.',
        ]);
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

    /**
     * Kosongkan pencarian tanpa perlu menghapus huruf satu per satu.
     */
    public function clearSearch(): void
    {
        $this->search = '';
    }

    /**
     * Pola LIKE untuk pencarian, atau null bila kotak pencarian kosong.
     * Wildcard bawaan pengguna di-escape supaya "%" tidak mencocokkan semua baris.
     */
    protected function searchTerm(): ?string
    {
        $term = trim($this->search);

        return $term === '' ? null : '%'.addcslashes($term, '%_\\').'%';
    }

    public function render()
    {
        $baseQuery = fn () => DuesRecord::query()
            ->forPeriod($this->year, $this->month)
            ->whereHas('member', function ($q): void {
                $q->where('group_id', $this->group->getKey())
                    ->where('status', MemberStatus::Aktif);
            });

        $totalCount = $baseQuery()->count();

        $records = $baseQuery()
            ->with('member')
            ->when($this->searchTerm(), function ($query, string $term): void {
                $query->whereHas('member', fn ($q) => $q->where(
                    fn ($s) => $s->where('name_indonesian', 'like', $term)
                        ->orWhere('name_pinyin', 'like', $term)
                        ->orWhere('name_hanzi', 'like', $term)
                        ->orWhere('member_number', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                ));
            })
            ->get()
            // Urut nomor anggota, sama dengan urutan buku kupon di tangan kolektor.
            ->sortBy(fn (DuesRecord $r) => $r->member->no_urut ?? PHP_INT_MAX)
            ->values();

        // Default pelunasan: bulan berjalan saja. Memilih bulan yang lebih jauh
        // harus jadi tindakan sadar kolektor — kalau default-nya Desember, satu
        // salah tekan "Lunasi" langsung mencatat setoran banyak bulan.
        foreach ($records as $record) {
            $this->payUntil[$record->member_id] ??= $this->month;
        }

        $activeMember = $this->activeMemberId !== null
            ? $records->firstWhere('member_id', $this->activeMemberId)?->member
            : null;

        return view('livewire.kolektor.billing', [
            'records' => $records,
            'activeMember' => $activeMember,
            'totalCount' => $totalCount,
            'isSearching' => $this->searchTerm() !== null,
            'period' => Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y'),
            'monthOptions' => $this->monthOptions(),
            'outcomeOptions' => VisitOutcome::cases(),
            'whatsappOwnerOptions' => WhatsappOwner::cases(),
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
