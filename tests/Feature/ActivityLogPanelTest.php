<?php

namespace Tests\Feature;

use App\Enums\ActivityCategory;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jejak audit adalah bacaan Super Admin saja, dan tidak boleh bisa dirapikan
 * oleh siapa pun — termasuk Super Admin sendiri.
 */
class ActivityLogPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_the_activity_log(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertTrue(ActivityLogResource::canAccess());
        $this->assertTrue(ActivityLogResource::canViewAny());
    }

    public function test_admin_cannot_open_the_activity_log(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertFalse(ActivityLogResource::canAccess());
        $this->assertFalse(ActivityLogResource::canViewAny());
    }

    public function test_collector_cannot_open_the_activity_log(): void
    {
        $this->actingAs(User::factory()->collector()->create());

        $this->assertFalse(ActivityLogResource::canAccess());
        $this->assertFalse(ActivityLogResource::canViewAny());
    }

    public function test_a_guest_cannot_open_the_activity_log(): void
    {
        $this->assertFalse(ActivityLogResource::canAccess());
    }

    /**
     * Menyembunyikan menunya tidak cukup: URL-nya tetap bisa diketik langsung.
     */
    public function test_admin_is_refused_at_the_activity_log_url_itself(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(ActivityLogResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_super_admin_is_served_the_activity_log_url(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(ActivityLogResource::getUrl('index'))
            ->assertOk();
    }

    /**
     * Jejak yang bisa dihapus bukan jejak audit. Tidak ada satu pun jalan
     * bawaan Filament untuk membuat, mengubah, atau menghapusnya.
     */
    public function test_the_log_can_never_be_written_to_from_the_panel(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $log = ActivityLog::query()->create(['log_name' => 'default', 'description' => 'apa saja']);

        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertFalse(ActivityLogResource::canEdit($log));
        $this->assertFalse(ActivityLogResource::canDelete($log));
        $this->assertFalse(ActivityLogResource::canDeleteAny());
        $this->assertSame(['index'], array_keys(ActivityLogResource::getPages()));
    }

    public function test_the_table_reads_as_indonesian_sentences(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $group = MemberGroup::factory()->create(['name' => 'Kelompok 3']);

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords(ActivityLog::query()->get())
            ->assertSee('Kelompok ditambahkan')
            ->assertSee('Kelompok 3');

        unset($group);
    }

    public function test_entries_can_be_filtered_by_category(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        Member::factory()->active()->create();
        $printed = ActivityLog::query()->create([
            'log_name' => ActivityCategory::Cetak->value,
            'description' => 'Cetak daftar anggota',
        ]);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('log_name', ActivityCategory::Cetak->value)
            ->assertCanSeeTableRecords([$printed])
            ->assertCanNotSeeTableRecords(
                ActivityLog::query()->where('log_name', ActivityCategory::PerubahanData->value)->get(),
            );
    }

    public function test_newest_entries_come_first(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $older = ActivityLog::query()->create(['log_name' => 'default', 'description' => 'lebih dulu']);
        $newer = ActivityLog::query()->create(['log_name' => 'default', 'description' => 'paling baru']);

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }
}
