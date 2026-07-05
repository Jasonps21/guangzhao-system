<?php

namespace Tests\Feature;

use App\Filament\Pages\PetaAnggota;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Widgets\LocationCoverageStats;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberLocationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_members_table_can_filter_those_missing_a_location(): void
    {
        $withLocation = Member::factory()->active()->withLocation()->create();
        $withoutLocation = Member::factory()->active()->create(['latitude' => null, 'longitude' => null]);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$withLocation, $withoutLocation])
            ->filterTable('missing_location')
            ->assertCanSeeTableRecords([$withoutLocation])
            ->assertCanNotSeeTableRecords([$withLocation]);
    }

    public function test_members_table_can_filter_those_missing_a_house_photo(): void
    {
        $withPhoto = Member::factory()->active()->withLocation()->create();
        $withoutPhoto = Member::factory()->active()->create(['house_photo_path' => null]);

        Livewire::test(ListMembers::class)
            ->filterTable('missing_house_photo')
            ->assertCanSeeTableRecords([$withoutPhoto])
            ->assertCanNotSeeTableRecords([$withPhoto]);
    }

    public function test_location_coverage_widget_renders(): void
    {
        Member::factory()->active()->withLocation()->count(2)->create();
        Member::factory()->active()->create(['latitude' => null, 'longitude' => null]);

        Livewire::test(LocationCoverageStats::class)
            ->assertOk()
            ->assertSee('Lokasi Rumah Lengkap');
    }

    public function test_map_page_lists_located_members(): void
    {
        $located = Member::factory()->active()->withLocation()->create(['name_indonesian' => 'Budi Petakan']);

        Livewire::test(PetaAnggota::class)
            ->assertOk()
            ->assertSee('member-map', escape: false)
            ->assertSee('Budi Petakan');
    }

    public function test_map_page_shows_empty_state_without_locations(): void
    {
        Member::factory()->active()->create(['latitude' => null, 'longitude' => null]);

        Livewire::test(PetaAnggota::class)
            ->assertOk()
            ->assertSee('Belum ada anggota aktif dengan titik lokasi');
    }

    public function test_member_edit_page_renders_location_section(): void
    {
        $member = Member::factory()->active()->withLocation()->create();

        Livewire::test(EditMember::class, ['record' => $member->id])
            ->assertOk()
            ->assertSee('Lokasi & Rumah');
    }
}
