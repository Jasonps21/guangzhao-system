<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\VisitOutcome;
use App\Livewire\Kolektor\Billing;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemberVisitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: MemberGroup}
     */
    private function collectorWithGroup(): array
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        return [$collector, $group];
    }

    public function test_payment_is_blocked_until_house_location_is_complete(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id)
            ->assertSet('activeMemberId', $member->id);

        // Belum lunas — kolektor diarahkan melengkapi lokasi dulu.
        $this->assertSame(DuesStatus::BelumBayar, $record->refresh()->status);
    }

    public function test_first_visit_requires_gps_and_photo(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->call('saveVisit')
            ->assertHasErrors(['lat', 'lng', 'photo']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    public function test_collector_records_a_visit_with_gps_and_photo(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['address' => 'Alamat lama']);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('houseAddress', 'Jl. Baru No. 5')
            ->set('note', 'pagar hijau, sebelah warung')
            ->call('saveVisit')
            ->assertHasNoErrors()
            ->assertSet('activeMemberId', null);

        $member->refresh();
        $this->assertTrue($member->hasCompleteLocation());
        $this->assertEqualsWithDelta(-5.1476, (float) $member->latitude, 0.0001);
        $this->assertSame('Jl. Baru No. 5', $member->address);
        $this->assertSame($collector->id, $member->location_updated_by);
        $this->assertNotNull($member->location_updated_at);
        Storage::disk('public')->assertExists($member->house_photo_path);

        $visit = MemberVisit::query()->firstOrFail();
        $this->assertSame($member->id, $visit->member_id);
        $this->assertSame($collector->id, $visit->user_id);
        $this->assertNotNull($visit->photo_path);
    }

    public function test_confirm_location_records_a_confirmation_visit(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('confirmLocation', $member->id);

        $this->assertSame(1, MemberVisit::query()->where('member_id', $member->id)->count());
        $this->assertSame($collector->id, $member->refresh()->location_updated_by);
        $this->assertSame(VisitOutcome::Bertemu, MemberVisit::query()->firstOrFail()->outcome);
    }

    public function test_already_located_member_can_pay_without_recapturing(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id);

        $this->assertSame(DuesStatus::Lunas, $record->refresh()->status);
    }

    public function test_member_exposes_google_maps_links_when_located(): void
    {
        $member = Member::factory()->withLocation()->create();

        $this->assertStringContainsString('-5.1476', (string) $member->googleMapsUrl());
        $this->assertStringContainsString('destination=', (string) $member->googleMapsDirectionsUrl());
        $this->assertNull(Member::factory()->create(['latitude' => null, 'longitude' => null])->googleMapsUrl());
    }

    /**
     * Jalan keluar saat foto gagal terkirim di lapangan: titik GPS tetap
     * tersimpan supaya perjalanan kolektor tidak terbuang.
     */
    public function test_collector_can_save_the_gps_point_when_the_photo_fails_to_upload(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('houseAddress', 'Jl. Anuang 25C')
            ->call('saveLocationOnly')
            ->assertHasNoErrors()
            ->assertSet('activeMemberId', null);

        $member->refresh();
        $this->assertEqualsWithDelta(-5.1476, (float) $member->latitude, 0.0001);
        $this->assertSame('Jl. Anuang 25C', $member->address);
        $this->assertSame(1, MemberVisit::query()->count());

        // Kebijakan "wajib sekali" tidak boleh ikut longgar: tanpa foto,
        // lokasinya tetap dianggap belum lengkap sehingga belum bisa lunas.
        $this->assertNull($member->house_photo_path);
        $this->assertFalse($member->hasCompleteLocation());
    }

    public function test_saving_the_gps_point_alone_still_requires_a_gps_point(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->call('saveLocationOnly')
            ->assertHasErrors(['lat', 'lng']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    /**
     * Iuran yang sudah dilunasi di kantor menutup seluruh zona aksi kartu,
     * sehingga tidak ada satu pun tombol untuk membuka panel kunjungan —
     * lokasi rumah anggota itu jadi mustahil dilengkapi kolektor.
     */
    public function test_location_entry_stays_reachable_after_the_office_marked_the_dues_paid(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $admin = User::factory()->create();
        $member = Member::factory()->active()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->paid()->create([
            'member_id' => $member->id,
            'collection_method' => CollectionMethod::Admin,
            'recorded_by' => $admin->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSeeHtml('wire:click="openVisit('.$member->id.')"');
    }

    public function test_collector_can_record_the_visit_of_a_member_already_marked_paid(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $admin = User::factory()->create();
        $member = Member::factory()->active()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->paid()->create([
            'member_id' => $member->id,
            'collection_method' => CollectionMethod::Admin,
            'recorded_by' => $admin->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->assertSee('Kunjungan Rumah')
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertTrue($member->refresh()->hasCompleteLocation());
    }

    public function test_a_paid_member_with_complete_location_keeps_the_change_location_action(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $admin = User::factory()->create();
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->paid()->create([
            'member_id' => $member->id,
            'collection_method' => CollectionMethod::Admin,
            'recorded_by' => $admin->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSeeHtml('wire:click="openVisit('.$member->id.')"')
            ->assertSeeHtml('wire:click="confirmLocation('.$member->id.')"');
    }
}
