<?php

namespace App\Filament\Pages;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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
     * Kelompok yang dipilih untuk difilter (null = semua).
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
        $points = Member::query()
            ->where('status', MemberStatus::Aktif)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($this->group, fn ($query) => $query->where('group_id', $this->group))
            ->with('group')
            ->get()
            ->map(fn (Member $member): array => [
                'name' => $member->displayName(),
                'group' => $member->group?->name,
                'lat' => (float) $member->latitude,
                'lng' => (float) $member->longitude,
                'directions' => $member->googleMapsDirectionsUrl(),
            ])
            ->values();

        return [
            'points' => $points,
            'groups' => MemberGroup::query()->orderBy('name')->pluck('name', 'id'),
            'selectedGroup' => $this->group,
        ];
    }
}
