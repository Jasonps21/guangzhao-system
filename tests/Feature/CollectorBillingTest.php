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

    public function test_pay_until_defaults_to_the_current_month_so_a_stray_tap_cannot_bill_many_months(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod(2026, 6)->create(['member_id' => $member->id]);

        // Kolektor tidak menyentuh dropdown sama sekali, lalu menekan "Lunasi".
        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->set('year', 2026)
            ->set('month', 6)
            ->assertSet("payUntil.{$member->id}", 6)
            ->call('payThrough', $member->id);

        // Hanya Juni yang tercatat — tidak ada setoran tujuh bulan.
        $this->assertSame(1, DuesRecord::query()->where('member_id', $member->id)->count());
        $this->assertDatabaseCount('payment_submissions', 0);
    }

    public function test_collector_can_undo_a_mistyped_payment_within_the_grace_window(): void
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
            ->call('markPaid', $record->id)
            ->call('undoPayment', $record->id);

        $record->refresh();
        $this->assertSame(DuesStatus::BelumBayar, $record->status);
        $this->assertNull($record->paid_at);
        $this->assertNull($record->amount_paid);
        $this->assertNull($record->recorded_by);
        $this->assertNull($record->collection_method);
    }

    public function test_undo_is_no_longer_offered_once_the_grace_window_has_passed(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        $component = Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id);

        $this->travel(DuesRecord::UNDO_WINDOW_MINUTES + 1)->minutes();

        $component->call('undoPayment', $record->id);

        // Tetap lunas: koreksi terlambat harus lewat admin.
        $this->assertSame(DuesStatus::Lunas, $record->refresh()->status);
    }

    public function test_a_collector_cannot_undo_a_payment_recorded_by_someone_else(): void
    {
        $collector = User::factory()->collector()->create();
        $otherCollector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);
        $otherCollector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($otherCollector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('undoPayment', $record->id);

        $this->assertSame(DuesStatus::Lunas, $record->refresh()->status);
        $this->assertSame($otherCollector->id, $record->recorded_by);
    }

    public function test_undo_cannot_touch_a_month_that_belongs_to_a_pending_submission(): void
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod(2026, 6)->create(['member_id' => $member->id]);

        $component = Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->set('year', 2026)
            ->set('month', 6)
            ->set("payUntil.{$member->id}", 12)
            ->call('payThrough', $member->id);

        $component->call('undoPayment', $record->id);

        $record->refresh();
        $this->assertSame(DuesStatus::MenungguPersetujuan, $record->status);
        $this->assertNotNull($record->submission_id);
    }

    public function test_confirming_the_location_is_unchanged_requires_confirmation(): void
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
            ->assertSeeHtml('wire:confirm="Rumah ');
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
