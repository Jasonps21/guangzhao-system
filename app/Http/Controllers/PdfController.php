<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\PrintedDocument;
use App\Models\CondolencePayment;
use App\Models\DuesReceipt;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
use App\Models\SocialFundRecord;
use App\Support\ActivityRecorder;
// Facade dan kelas konkretnya hanya beda huruf besar-kecil (Pdf vs PDF), dan PHP
// tidak membedakannya — kelas konkretnya dialiaskan agar keduanya tidak tertukar.
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PdfController extends Controller
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    /**
     * Kupon iuran — F4 (215 × 330 mm), 10 kupon/lembar (2 × 5). (§F, §6.7)
     *
     * Menata ratusan kupon memakan puluhan detik, dan petugas yang mengira
     * cetakannya belum jalan sering menekan tombol lagi. Hasilnya karena itu
     * disimpan di cache dan dijaga kunci: penekanan kedua ikut menunggu cetakan
     * yang sedang berjalan, lalu langsung menerima berkas yang sama — bukan
     * memulai render kedua yang membebani server dua kali lipat.
     */
    public function coupons(Request $request): Response
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);
        $groupId = $request->integer('group') ?: null;

        $records = $this->couponRecords($year, $month, $groupId);
        $organization = OrganizationSetting::current();

        $key = 'kupon:'.md5(implode('|', [
            $year,
            $month,
            $groupId ?? 'semua',
            // Sidik jari dari isi datanya sendiri, bukan waktu ubahnya: perbaikan
            // langsung di database tidak menyentuh updated_at, dan dulu kupon
            // lama dengan no. urut kembar tetap tersaji setelah dibetulkan.
            md5(serialize($records->map(fn (DuesRecord $record): array => [
                $record->getAttributes(),
                $record->member->getAttributes(),
                $record->member->group?->getAttributes(),
            ])->all())),
            md5(serialize($organization->getAttributes())),
        ]));

        // Disimpan sebagai base64: cache produksi memakai driver database, dan
        // kolom `cache.value` bertipe mediumtext utf8mb4 — byte mentah PDF
        // ditolak MariaDB dengan "Incorrect string value".
        $build = fn (): string => base64_encode($this->render('pdf.coupons', [
            'records' => $records,
            'period' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'organization' => $organization,
            // Watermark diulang di tiap kupon — pakai path, bukan data URI. (§logoPath)
            'logo' => $this->logoPath($organization),
        ])->setPaper([0, 0, 609.45, 935.43])->output()); // F4 215×330mm in points

        $encoded = Cache::get($key) ?? Cache::lock($key.':lock', 300)->block(
            300,
            fn (): string => Cache::remember($key, now()->addHours(6), $build),
        );

        $this->activity->printed(PrintedDocument::KuponIuran, [
            'periode' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'kelompok' => $groupId ? MemberGroup::find($groupId)?->name : 'Semua kelompok',
            'jumlah_kupon' => $records->count(),
        ]);

        return response(base64_decode($encoded, strict: true), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="kupon-iuran-'.$year.'-'.$month.'.pdf"',
        ]);
    }

    /**
     * Tagihan yang layak dicetak jadi kupon, sudah terurut siap dibagikan.
     *
     * Hanya tagihan BELUM BAYAR. Kupon adalah surat tagihan yang dibawa
     * kolektor, bukan arsip: anggota yang sudah melunasi bulan itu — misalnya
     * membayar Januari–Desember sekaligus di kantor — akan ditagih untuk kedua
     * kalinya bila kuponnya tetap ikut tercetak. Setoran kolektor yang masih
     * menunggu persetujuan juga dilewati karena uangnya sudah berpindah tangan
     * di lapangan; bila setorannya ditolak, tagihannya kembali BELUM BAYAR dan
     * ikut tercetak lagi dengan sendirinya.
     *
     * @return Collection<int, DuesRecord>
     */
    private function couponRecords(int $year, int $month, ?int $groupId): Collection
    {
        return DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod($year, $month)
            ->unpaid()
            ->whereHas('member', function ($query) use ($groupId): void {
                $query->where('status', MemberStatus::Aktif);
                if ($groupId) {
                    $query->where('group_id', $groupId);
                }
            })
            ->get()
            // §F — urutkan per kelompok lalu nomor urut anggota agar mudah dibagikan setelah dicetak.
            ->sortBy(fn (DuesRecord $r) => [
                $r->member->group?->name,
                $r->member->no_urut ?? PHP_INT_MAX,
                $r->member->member_number,
            ])
            ->values();
    }

    /**
     * Kupon iuran untuk anggota yang dipilih satu per satu — untuk kupon yang
     * robek atau kena air, dan untuk anggota yang tagihannya belum digenerate.
     *
     * Pilihannya boleh lintas kelompok, jadi lembarnya tidak dipisah per
     * kelompok: tiga nama dari tiga kelompok muat di satu lembar, bukan tiga
     * lembar yang hampir kosong semua. Tidak di-cache seperti cetak per
     * kelompok — hasilnya umumnya satu lembar, dan tanpa cache setiap cetak
     * ulang otomatis memakai data anggota terbaru.
     */
    public function selectedCoupons(Request $request): Response
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);
        /** @var list<int> $memberIds */
        $memberIds = array_map('intval', (array) $request->input('members', []));

        $organization = OrganizationSetting::current();
        $records = $this->recordsForMembers($year, $month, $memberIds);

        $pdf = $this->render('pdf.coupons', [
            'records' => $records,
            'period' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'organization' => $organization,
            // Watermark diulang di tiap kupon — pakai path, bukan data URI. (§logoPath)
            'logo' => $this->logoPath($organization),
            'splitPerGroup' => false,
        ])->setPaper([0, 0, 609.45, 935.43]); // F4 215×330mm in points

        $this->activity->printed(PrintedDocument::KuponTerpilih, [
            'periode' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'jumlah_kupon' => $records->count(),
            'anggota' => $records->map(fn (DuesRecord $r): ?string => $r->member?->displayName())->filter()->values()->all(),
        ]);

        return $pdf->stream('kupon-pilihan-'.$year.'-'.$month.'.pdf');
    }

    /**
     * Tagihan periode tersebut untuk anggota terpilih, siap dicetak.
     *
     * Anggota yang belum punya tagihan tetap dapat kupon: dibuatkan objek
     * tagihan yang TIDAK disimpan, bernominal iuran bulanan dari master data —
     * sumber yang sama dipakai DuesGenerator. Mencetak kupon bukan menagih,
     * jadi tidak ada satu baris pun yang ditulis ke database.
     *
     * Status anggota disaring di sini, bukan hanya di pencarian, supaya id yang
     * diketik langsung di URL pun tidak menembusnya.
     *
     * @param  list<int>  $memberIds
     * @return Collection<int, DuesRecord>
     */
    private function recordsForMembers(int $year, int $month, array $memberIds): Collection
    {
        $members = Member::query()
            ->with('group')
            ->whereKey($memberIds)
            ->where('status', MemberStatus::Aktif)
            ->get();

        $existing = DuesRecord::query()
            ->forPeriod($year, $month)
            ->whereIn('member_id', $members->modelKeys())
            ->get()
            ->keyBy('member_id');

        return $members
            ->map(function (Member $member) use ($existing, $year, $month): DuesRecord {
                $record = $existing->get($member->getKey()) ?? new DuesRecord([
                    'period_year' => $year,
                    'period_month' => $month,
                    'amount_due' => $member->monthly_dues_amount,
                ]);

                $record->setRelation('member', $member);

                return $record;
            })
            // Urutan sama dengan cetak per kelompok agar hasilnya mudah dicocokkan.
            ->sortBy(fn (DuesRecord $record) => [
                $record->member->group?->name,
                $record->member->no_urut ?? PHP_INT_MAX,
                $record->member->member_number,
            ])
            ->values();
    }

    /**
     * Daftar anggota per kelompok — A4, satu kelompok per halaman. (§6.x)
     *
     * Kolom: nomor urut, nama hanzi, nama Indonesia, alamat, telepon,
     * dan kolom keterangan yang sengaja dikosongkan untuk diisi manual.
     */
    public function memberList(Request $request): Response
    {
        $groupId = $request->integer('group') ?: null;
        $activeOnly = $request->boolean('active_only', true);

        $sortMembers = fn ($query) => $query
            ->when($activeOnly, fn ($q) => $q->where('status', MemberStatus::Aktif))
            ->orderByRaw('no_urut IS NULL, no_urut')
            ->orderBy('member_number');

        $groups = MemberGroup::query()
            ->when($groupId, fn ($query) => $query->whereKey($groupId))
            ->with(['members' => $sortMembers])
            ->orderBy('name')
            ->get();

        // §6.x — anggota tanpa kelompok tetap perlu tercetak (kecuali saat memfilter satu kelompok).
        if (! $groupId) {
            $ungrouped = $sortMembers(Member::query()->whereNull('group_id'))->get();

            if ($ungrouped->isNotEmpty()) {
                $placeholder = new MemberGroup(['name' => 'Tanpa Kelompok']);
                $placeholder->setRelation('members', $ungrouped);
                $groups->push($placeholder);
            }
        }

        $organization = OrganizationSetting::current();

        $pdf = $this->render('pdf.member-list', [
            'groups' => $groups,
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
            'printedAt' => now()->translatedFormat('d F Y H:i'),
        ])->setPaper('a4', 'landscape');

        $this->activity->printed(PrintedDocument::DaftarAnggota, [
            'kelompok' => $groupId ? MemberGroup::find($groupId)?->name : 'Semua kelompok',
            'hanya_anggota_aktif' => $activeOnly ? 'Ya' : 'Tidak',
            'jumlah_anggota' => $groups->sum(fn (MemberGroup $group): int => $group->members->count()),
        ]);

        return $pdf->stream('daftar-anggota.pdf');
    }

    /**
     * Kartu anggota — 86 × 54 mm. (§G)
     */
    public function memberCard(Member $member): Response
    {
        $organization = OrganizationSetting::current();

        $pdf = $this->render('pdf.member-card', [
            'member' => $member->load('group'),
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
        ])->setPaper([0, 0, 243.78, 153.07]); // 86×54mm in points

        $this->activity->printed(PrintedDocument::KartuAnggota, [
            'no_anggota' => $member->member_number,
            'nama' => $member->displayName(),
        ], $member);

        return $pdf->stream("kartu-{$member->member_number}.pdf");
    }

    /**
     * Nota uang duka — TANDA TERIMA yang ditandatangani keluarga penerima.
     */
    public function condolenceReceipt(CondolencePayment $payment): Response
    {
        $this->activity->printed(PrintedDocument::TandaTerimaUangDuka, $this->condolenceDetails($payment), $payment);

        return $this->condolenceNote($payment, 'terima');
    }

    /**
     * Nota uang duka — TANDA PEMBERIAN yang ditandatangani pengurus (福利部).
     */
    public function condolenceHandover(CondolencePayment $payment): Response
    {
        $this->activity->printed(PrintedDocument::TandaPemberianUangDuka, $this->condolenceDetails($payment), $payment);

        return $this->condolenceNote($payment, 'pemberian');
    }

    /**
     * Nota TANDA TERIMA dana sosial (sumbangan masuk / bantuan keluar).
     */
    public function socialFundReceipt(SocialFundRecord $record): Response
    {
        $organization = OrganizationSetting::current();

        $pdf = $this->render('pdf.social-fund-receipt', [
            'record' => $record,
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
        ])->setPaper('a5', 'landscape');

        $this->activity->printed(PrintedDocument::NotaDanaSosial, [
            'no_nota' => $record->receipt_number,
            'pihak' => $record->party_name,
            'jumlah' => $record->amount !== null ? (float) $record->amount : null,
        ], $record);

        return $pdf->stream('tanda-terima-'.$record->receipt_number.'.pdf');
    }

    /**
     * Nota TANDA TERIMA pembayaran iuran beberapa bulan sekaligus.
     */
    public function duesReceipt(DuesReceipt $receipt): Response
    {
        $organization = OrganizationSetting::current();

        $pdf = $this->render('pdf.dues-receipt', [
            'receipt' => $receipt->load(['member', 'issuedBy']),
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
        ])->setPaper('a5', 'landscape');

        $this->activity->printed(PrintedDocument::NotaPembayaranIuran, [
            'no_nota' => $receipt->receipt_number,
            'anggota' => $receipt->member?->displayName(),
            'periode' => $receipt->periodRangeLabel(),
            'jumlah' => (float) $receipt->total_amount,
        ], $receipt);

        return $pdf->stream('tanda-terima-iuran-'.$receipt->receipt_number.'.pdf');
    }

    /**
     * Detail yang sama dipakai kedua nota uang duka.
     *
     * @return array<string, mixed>
     */
    private function condolenceDetails(CondolencePayment $payment): array
    {
        return [
            'no_nota' => $payment->receipt_number,
            'penerima' => $payment->recipient_name,
            'jumlah' => (float) $payment->amount,
        ];
    }

    /**
     * Kedua nota uang duka dicetak dari satu template yang sama; hanya bunyi
     * kalimat dan blok tanda tangannya yang berbeda. (§H)
     */
    private function condolenceNote(CondolencePayment $payment, string $variant): Response
    {
        $organization = OrganizationSetting::current();

        $pdf = $this->render('pdf.condolence-note', [
            'payment' => $payment,
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
            'variant' => $variant,
        ])->setPaper('a5', 'landscape');

        return $pdf->stream('uang-duka-'.$variant.'-'.$payment->receipt_number.'.pdf');
    }

    /**
     * Semua template di sini memuat font CJK WenQuanYi berukuran 11,6 MB.
     * Tanpa subsetting DomPDF menanam SELURUH font di tiap berkas, sehingga nota
     * satu halaman pun berbobot ±6,8 MB dan lama diunduh. Dengan subsetting hanya
     * glif yang benar-benar dipakai yang ikut — hasil cetaknya identik, berkasnya
     * turun ke ±0,2 MB.
     *
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data): DomPdfDocument
    {
        return Pdf::loadView($view, $data)->setOption('enable_font_subsetting', true);
    }

    /**
     * Berkas logo di disk, untuk template yang mengulang gambarnya banyak kali.
     *
     * Kupon menampilkan watermark di tiap kupon. Bila logonya disisipkan sebagai
     * data URI, string base64 ±277 KB ikut tersalin ratusan kali — HTML-nya
     * membengkak sampai ratusan MB sebelum DomPDF sempat bekerja. Lewat path,
     * DomPDF membaca berkasnya sekali dan meng-cache-nya.
     */
    private function logoPath(OrganizationSetting $organization): ?string
    {
        $uploaded = $organization->logo_path;

        if ($uploaded !== null && Storage::disk('public')->exists($uploaded)) {
            return Storage::disk('public')->path($uploaded);
        }

        $bundled = public_path('images/logo-guangzao.png');

        return is_file($bundled) ? $bundled : null;
    }

    /**
     * Logo perkumpulan sebagai data URI base64 agar selalu ter-embed di PDF
     * tanpa bergantung pada akses file/remote DomPDF. Memakai logo yang diunggah
     * admin bila ada; jika tidak, jatuh ke logo bawaan. Null bila keduanya kosong.
     */
    private function logoDataUri(OrganizationSetting $organization): ?string
    {
        $uploaded = $organization->logo_path;

        if ($uploaded !== null && Storage::disk('public')->exists($uploaded)) {
            $mime = Storage::disk('public')->mimeType($uploaded) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($uploaded));
        }

        $bundled = public_path('images/logo-guangzao.png');

        if (! is_file($bundled)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($bundled));
    }

    /**
     * PWA manifest untuk seluruh aplikasi.
     *
     * Tombol "Pasang" browser muncul di halaman /masuk, yang dipakai semua
     * peran. Karena itu start_url harus menunjuk ke gerbang masuk — gerbanglah
     * yang tahu peran penggunanya — bukan langsung ke mode kolektor. Sebelumnya
     * start_url menunjuk ke /kolektor, sehingga pengurus yang memasang aplikasi
     * selalu mendarat di halaman kolektor tiap kali membukanya kembali.
     */
    public function manifest(): JsonResponse
    {
        return response()->json([
            // Identitas dikunci ke start_url lama agar ponsel kolektor yang
            // sudah memasang aplikasi memperlakukan manifest ini sebagai
            // pembaruan, bukan sebagai aplikasi baru.
            'id' => '/kolektor',
            'name' => config('app.name'),
            'short_name' => 'Iuran',
            'start_url' => route('login'),
            // Seluruh aplikasi, supaya panel admin tidak dianggap situs luar
            // dan dibuka di tab browser terpisah.
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f4f2ef',
            'theme_color' => '#f4f2ef',
            // Lambang perkumpulan sebagai ikon layar utama, supaya kolektor
            // mengenali aplikasinya dari ikon dan bukan dari huruf bawaan browser.
            'icons' => [
                [
                    'src' => asset('images/logo-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('images/logo-guangzao.png'),
                    'sizes' => '352x352',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ]);
    }
}
