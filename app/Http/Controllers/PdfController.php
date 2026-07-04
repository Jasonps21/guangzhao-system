<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PdfController extends Controller
{
    /**
     * Kupon iuran — F4 (215 × 330 mm), 10 kupon/lembar (2 × 5). (§F, §6.7)
     */
    public function coupons(Request $request): Response
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);
        $groupId = $request->integer('group') ?: null;

        $records = DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod($year, $month)
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

        $organization = OrganizationSetting::current();

        $pdf = Pdf::loadView('pdf.coupons', [
            'records' => $records,
            'period' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
        ])->setPaper([0, 0, 609.45, 935.43]); // F4 215×330mm in points

        return $pdf->stream("kupon-iuran-{$year}-{$month}.pdf");
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

        $pdf = Pdf::loadView('pdf.member-list', [
            'groups' => $groups,
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
            'printedAt' => now()->translatedFormat('d F Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('daftar-anggota.pdf');
    }

    /**
     * Kartu anggota — 86 × 54 mm. (§G)
     */
    public function memberCard(Member $member): Response
    {
        $organization = OrganizationSetting::current();

        $pdf = Pdf::loadView('pdf.member-card', [
            'member' => $member->load('group'),
            'organization' => $organization,
            'logo' => $this->logoDataUri($organization),
        ])->setPaper([0, 0, 243.78, 153.07]); // 86×54mm in points

        return $pdf->stream("kartu-{$member->member_number}.pdf");
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
     * PWA manifest untuk mode kolektor.
     */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name').' — Kolektor',
            'short_name' => 'Iuran',
            'start_url' => route('kolektor.groups'),
            'scope' => '/kolektor',
            'display' => 'standalone',
            'background_color' => '#f5f5f4',
            'theme_color' => '#b45309',
            'icons' => [],
        ]);
    }
}
