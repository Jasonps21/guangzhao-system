<?php

namespace Tests\Feature;

use App\Enums\DuesStatus;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use App\Support\DuesGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daftar kolektor digerakkan oleh DuesRecord, bukan Member. Anggota yang
 * dimasukkan lewat admin tanpa tagihan bulan berjalan tidak terlihat di HP
 * kolektor maupun pencatat lokasi.
 */
class NewMemberGetsCurrentBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_member_in_admin_bills_the_current_month(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $now = Carbon::now();

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Fanny Lauwren',
                'group_id' => $group->id,
                'no_urut' => 142,
                'monthly_dues_amount' => 15000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::where('name_indonesian', 'Fanny Lauwren')->firstOrFail();

        $this->assertDatabaseHas('dues_records', [
            'member_id' => $member->id,
            'period_year' => $now->year,
            'period_month' => $now->month,
            'amount_due' => 15000.00,
            'status' => DuesStatus::BelumBayar->value,
        ]);
    }

    public function test_generating_for_a_member_is_idempotent(): void
    {
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 15000]);
        $generator = app(DuesGenerator::class);

        $this->assertTrue($generator->generateForMember($member, 2026, 10));
        $this->assertFalse($generator->generateForMember($member, 2026, 10));
        $this->assertDatabaseCount('dues_records', 1);
    }

    public function test_inactive_member_is_not_billed(): void
    {
        $member = Member::factory()->deceased()->create();

        $this->assertFalse(app(DuesGenerator::class)->generateForMember($member, 2026, 10));
        $this->assertDatabaseCount('dues_records', 0);
    }
}
