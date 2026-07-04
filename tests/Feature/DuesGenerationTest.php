<?php

namespace Tests\Feature;

use App\Enums\DuesStatus;
use App\Models\Member;
use App\Support\DuesGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuesGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_belum_bayar_records_for_active_members(): void
    {
        Member::factory(3)->active()->create(['monthly_dues_amount' => 30000]);

        $created = app(DuesGenerator::class)->generate(2026, 6);

        $this->assertSame(3, $created);
        $this->assertDatabaseCount('dues_records', 3);
        $this->assertDatabaseHas('dues_records', [
            'period_year' => 2026,
            'period_month' => 6,
            'amount_due' => 30000.00,
            'status' => DuesStatus::BelumBayar->value,
        ]);
    }

    public function test_inactive_members_are_excluded(): void
    {
        Member::factory()->active()->create();
        Member::factory()->deceased()->create();

        $created = app(DuesGenerator::class)->generate(2026, 6);

        $this->assertSame(1, $created);
    }

    public function test_it_is_idempotent(): void
    {
        Member::factory(2)->active()->create();

        app(DuesGenerator::class)->generate(2026, 6);
        $secondRun = app(DuesGenerator::class)->generate(2026, 6);

        $this->assertSame(0, $secondRun);
        $this->assertDatabaseCount('dues_records', 2);
    }

    public function test_it_snapshots_the_amount_at_generation_time(): void
    {
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 15000]);

        app(DuesGenerator::class)->generate(2026, 6);

        $member->update(['monthly_dues_amount' => 100000]);
        app(DuesGenerator::class)->generate(2026, 6);

        $this->assertSame('15000.00', $member->duesRecords()->first()->amount_due);
    }

    public function test_the_command_generates_dues(): void
    {
        Member::factory(2)->active()->create();

        $this->artisan('dues:generate', ['year' => 2026, 'month' => 6])
            ->assertSuccessful();

        $this->assertDatabaseCount('dues_records', 2);
    }
}
