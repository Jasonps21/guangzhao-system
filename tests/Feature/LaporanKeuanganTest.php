<?php

namespace Tests\Feature;

use App\Filament\Pages\LaporanKeuangan;
use App\Filament\Widgets\DuesArrearsList;
use App\Filament\Widgets\DuesGroupSummary;
use App\Filament\Widgets\DuesReportStats;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanKeuanganTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_page_renders_with_default_period(): void
    {
        Livewire::test(LaporanKeuangan::class)
            ->assertOk()
            ->assertSet('period', now()->format('Y-m'));
    }

    public function test_stats_widget_renders_collected_and_arrears(): void
    {
        DuesRecord::factory()->for(Member::factory())->paid()->create(['amount_paid' => 50000]);
        DuesRecord::factory()->for(Member::factory())->create(['amount_due' => 30000]);

        Livewire::test(DuesReportStats::class, [
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ])
            ->assertOk()
            ->assertSee('Terkumpul')
            ->assertSee('Tunggakan')
            ->assertSee('Rp 50.000')
            ->assertSee('Rp 30.000');
    }

    public function test_group_summary_widget_lists_groups(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok Mawar']);
        $member = Member::factory()->create(['group_id' => $group->id]);

        DuesRecord::factory()->for($member)->paid()->create(['amount_paid' => 75000]);

        Livewire::test(DuesGroupSummary::class, [
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ])
            ->assertOk()
            ->assertSee('Kelompok Mawar')
            ->assertSee('Rp 75.000');
    }

    public function test_arrears_widget_lists_unpaid_members(): void
    {
        $member = Member::factory()->create(['name_indonesian' => 'Budi Santoso']);
        DuesRecord::factory()->for($member)->create(['amount_due' => 25000]);

        Livewire::test(DuesArrearsList::class, [
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ])
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Rp 25.000');
    }
}
