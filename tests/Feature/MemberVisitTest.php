<?php

namespace Tests\Feature;

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
}
