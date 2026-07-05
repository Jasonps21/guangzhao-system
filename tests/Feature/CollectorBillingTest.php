<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Livewire\Kolektor\Billing;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CollectorBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_can_mark_a_member_paid_in_the_field(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id);

        $record->refresh();
        $this->assertSame(DuesStatus::Lunas, $record->status);
        $this->assertSame(CollectionMethod::Lapangan, $record->collection_method);
        $this->assertSame($collector->id, $record->recorded_by);
    }

    public function test_the_lunas_button_requires_confirmation_to_avoid_accidental_taps(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSeeHtml('wire:confirm="Tandai LUNAS');
    }

    public function test_collector_cannot_open_a_group_they_are_not_assigned_to(): void
    {
        $collector = User::factory()->collector()->create();
        $otherGroup = MemberGroup::factory()->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $otherGroup])
            ->assertForbidden();
    }

    public function test_collector_multi_month_payment_waits_for_admin_approval(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        // Tagihan terbit Juni; anggota ingin lunas penuh hingga Desember.
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create([
            'monthly_dues_amount' => 30000,
        ]);
        $juneRecord = DuesRecord::factory()->forPeriod(2026, 6)->create([
            'member_id' => $member->id,
            'amount_due' => 30000,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->set('year', 2026)
            ->set('month', 6)
            ->set("payUntil.{$member->id}", 12)
            ->call('payThrough', $member->id);

        // Juni–Desember = 7 bulan, semua MENUNGGU PERSETUJUAN (belum lunas).
        $pending = DuesRecord::query()
            ->where('member_id', $member->id)
            ->where('period_year', 2026)
            ->whereBetween('period_month', [6, 12])
            ->where('status', DuesStatus::MenungguPersetujuan)
            ->get();

        $this->assertCount(7, $pending);
        $this->assertTrue($pending->every(fn (DuesRecord $r) => $r->collection_method === CollectionMethod::Lapangan));
        $this->assertTrue($pending->every(fn (DuesRecord $r) => $r->recorded_by === $collector->id));
        $this->assertTrue($pending->every(fn (DuesRecord $r) => $r->paid_at === null));

        $this->assertDatabaseHas('payment_submissions', [
            'member_id' => $member->id,
            'collector_id' => $collector->id,
            'period_year' => 2026,
            'from_month' => 6,
            'to_month' => 12,
            'total_amount' => 210000.00,
            'status' => 'pending',
        ]);

        $juneRecord->refresh();
        $this->assertSame(DuesStatus::MenungguPersetujuan, $juneRecord->status);
    }

    public function test_collector_paying_a_single_month_via_pay_through_is_instant(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create(['monthly_dues_amount' => 30000]);
        $record = DuesRecord::factory()->forPeriod(2026, 6)->create([
            'member_id' => $member->id,
            'amount_due' => 30000,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->set('year', 2026)
            ->set('month', 6)
            ->set("payUntil.{$member->id}", 6)
            ->call('payThrough', $member->id);

        $this->assertSame(DuesStatus::Lunas, $record->refresh()->status);
        $this->assertDatabaseCount('payment_submissions', 0);
    }

    public function test_collector_cannot_pay_a_member_outside_their_group(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $outsideMember = Member::factory()->active()->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->set('year', 2026)
            ->set('month', 6)
            ->call('payThrough', $outsideMember->id)
            ->assertStatus(404);

        $this->assertSame(0, DuesRecord::query()->where('member_id', $outsideMember->id)->count());
    }
}
