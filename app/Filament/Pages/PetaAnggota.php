<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class PetaAnggota extends Page
{
    protected string $view = 'filament.pages.peta-anggota';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Peta Rumah';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Peta Rumah Anggota';

    /**
     * Warna penanda kelompok, dipakai bersama oleh titik peta, daftar anggota,
     * dan penyaring kelompok — satu kelompok selalu tampil dengan satu warna.
     * Urutannya sengaja berselang-seling supaya kelompok bernomor berdekatan
     * tidak berwarna mirip.
     *
     * @var list<string>
     */
    private const GROUP_COLORS = [
        '#d97706', // amber (warna panel)
        '#2563eb', // blue
        '#059669', // emerald
        '#e11d48', // rose
        '#7c3aed', // violet
        '#0891b2', // cyan
        '#ea580c', // orange
        '#65a30d', // lime
        '#c026d3', // fuchsia
        '#0f766e', // teal
    ];

    /**
     * Warna untuk anggota yang belum masuk kelompok mana pun.
     */
    private const UNGROUPED_COLOR = '#64748b';

    /**
     * Kelompok yang dipilih untuk difilter (null = semua). Penyaringannya
     * berjalan di sisi browser, jadi nilai ini hanya menentukan pilihan awal
     * agar tautan berparameter `?group=` tetap berfungsi.
     */
    public ?int $group = null;

    public function mount(): void
    {
        $this->group = request()->integer('group') ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $groups = MemberGroup::query()->orderBy('name')->get();
        $colors = $this->groupColors($groups);
        $canViewMembers = auth()->user()?->can('view', Member::class) ?? false;

        $points = Member::query()
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('group')
            ->get()
            ->map(fn (Member $member): array => [
                'id' => $member->id,
                'number' => $member->member_number,
                'name' => $member->displayName(),
                'initial' => mb_strtoupper(mb_substr($member->displayName(), 0, 1)),
                'groupId' => $member->group_id,
                'group' => $member->group?->name,
                'color' => $colors[$member->group_id] ?? self::UNGROUPED_COLOR,
                'address' => $member->address,
                'phone' => $member->phone,
                'whatsapp' => $member->whatsapp_phone,
                'whatsappUrl' => $this->whatsappUrl($member->whatsapp_phone ?: $member->phone),
                'whatsappOwner' => $member->whatsapp_owner?->getLabel(),
                'photo' => $member->housePhotoUrl(),
                'lat' => (float) $member->latitude,
                'lng' => (float) $member->longitude,
                'maps' => $member->googleMapsUrl(),
                'directions' => $member->googleMapsDirectionsUrl(),
                'detail' => $canViewMembers
                    ? MemberResource::getUrl('edit', ['record' => $member])
                    : null,
            ])
            ->sortBy(fn (array $point): string => mb_strtolower($point['name']), SORT_NATURAL)
            ->values();

        $activeTotal = Member::query()->active()->count();

        return [
            'points' => $points,
            'selectedGroup' => $this->group,
            'activeTotal' => $activeTotal,
            'mappedTotal' => $points->count(),
            'withPhoto' => $points->whereNotNull('photo')->count(),
            'withContact' => $points->filter(
                fn (array $point): bool => filled($point['phone']) || filled($point['whatsapp'])
            )->count(),
            // Hanya kelompok yang benar-benar punya titik yang ditawarkan sebagai
            // penyaring; tombol kelompok kosong hanya menambah kebisingan.
            'groupFilters' => $groups
                ->map(fn (MemberGroup $group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'color' => $colors[$group->id],
                    'count' => $points->where('groupId', $group->id)->count(),
                ])
                ->filter(fn (array $filter): bool => $filter['count'] > 0)
                ->values(),
            'ungroupedCount' => $points->whereNull('groupId')->count(),
            'ungroupedColor' => self::UNGROUPED_COLOR,
        ];
    }

    /**
     * Peta id kelompok ke warna penanda, dibagi rata mengikuti urutan nama
     * kelompok sehingga warnanya tetap sama pada setiap kali halaman dibuka.
     *
     * @param  Collection<int, MemberGroup>  $groups
     * @return array<int, string>
     */
    private function groupColors(Collection $groups): array
    {
        return $groups
            ->values()
            ->mapWithKeys(fn (MemberGroup $group, int $index): array => [
                $group->id => self::GROUP_COLORS[$index % count(self::GROUP_COLORS)],
            ])
            ->all();
    }

    /**
     * Tautan percakapan WhatsApp untuk nomor yang tercatat. Null bila nomornya
     * kosong atau tidak masuk akal, supaya tombolnya tidak muncul sia-sia.
     */
    private function whatsappUrl(?string $raw): ?string
    {
        if (! PhoneNumber::isValid($raw)) {
            return null;
        }

        $number = (string) PhoneNumber::normalize($raw);

        return 'https://wa.me/62'.ltrim($number, '0');
    }
}
