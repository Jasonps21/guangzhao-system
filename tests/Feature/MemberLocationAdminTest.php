<?php

namespace Tests\Feature;

use App\Filament\Pages\PetaAnggota;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Widgets\LocationCoverageStats;
use App\Models\Member;
use App\Models\MemberGroup;
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

    public function test_map_page_carries_photo_contact_and_map_links_for_each_point(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok Berpeta']);

        Member::factory()->active()->withLocation()->create([
            'name_indonesian' => 'Budi Petakan',
            'group_id' => $group->id,
            'house_photo_path' => 'member-houses/rumah-budi.jpg',
            'phone' => '0812 3456 7890',
            'whatsapp_phone' => '0813 5555 1234',
        ]);

        Livewire::test(PetaAnggota::class)
            ->assertOk()
            ->assertSee('Kelompok Berpeta')
            ->assertSee('/storage/member-houses/rumah-budi.jpg', escape: false)
            ->assertSee('0812 3456 7890')
            ->assertSee('https://wa.me/6281355551234', escape: false)
            ->assertSee('https://www.google.com/maps/dir/?api=1', escape: false)
            ->assertSee('https://www.google.com/maps/search/?api=1', escape: false);
    }

    public function test_map_page_omits_the_whatsapp_link_when_no_number_is_usable(): void
    {
        Member::factory()->active()->withLocation()->create([
            'phone' => 'tidak ada',
            'whatsapp_phone' => null,
        ]);

        Livewire::test(PetaAnggota::class)
            ->assertOk()
            ->assertDontSee('wa.me', escape: false);
    }

    public function test_map_page_only_offers_groups_that_have_a_point(): void
    {
        $berisi = MemberGroup::factory()->create(['name' => 'Kelompok Berisi']);
        $kosong = MemberGroup::factory()->create(['name' => 'Kelompok Kosong']);

        Member::factory()->active()->withLocation()->create(['group_id' => $berisi->id]);
        Member::factory()->active()->create([
            'group_id' => $kosong->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        Livewire::test(PetaAnggota::class)
            ->assertOk()
            ->assertSee('Kelompok Berisi')
            ->assertDontSee('Kelompok Kosong');
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
