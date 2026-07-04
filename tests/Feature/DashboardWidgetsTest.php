<?php

namespace Tests\Feature;

use App\Filament\Widgets\DuesArrearsList;
use App\Filament\Widgets\DuesGroupSummary;
use App\Filament\Widgets\DuesReportStats;
use App\Filament\Widgets\DuesStatsOverview;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_period_scoped_report_widgets_are_not_registered_on_the_panel(): void
    {
        $widgets = Filament::getWidgets();

        $this->assertContains(DuesStatsOverview::class, $widgets);
        $this->assertNotContains(DuesReportStats::class, $widgets);
        $this->assertNotContains(DuesGroupSummary::class, $widgets);
        $this->assertNotContains(DuesArrearsList::class, $widgets);
    }
}
