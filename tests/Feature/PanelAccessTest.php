<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_admin_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue(User::factory()->superAdmin()->create()->canAccessPanel($panel));
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue(User::factory()->admin()->create()->canAccessPanel($panel));
    }

    public function test_collector_cannot_access_admin_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse(User::factory()->collector()->create()->canAccessPanel($panel));
    }

    public function test_inactive_manager_cannot_access_admin_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertFalse(User::factory()->admin()->create(['is_active' => false])->canAccessPanel($panel));
    }
}
