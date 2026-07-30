<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Filament\Pages\PembayaranKantor;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PembayaranKantorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_pay_several_months_at_the_office_instantly(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->callAction('bayarIuran', data: [
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 1,
                'to_month' => 6,
                'total_amount' => 150000,
                'received_by_name' => $admin->name,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $paid = $member->duesRecords()->where('status', DuesStatus::Lunas)->get();
        $this->assertCount(6, $paid);
        $this->assertTrue($paid->every(fn ($r) => $r->collection_method === CollectionMethod::Admin));
        $this->assertDatabaseCount('payment_submissions', 0);
    }

    public function test_end_month_cannot_be_before_start_month(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->callAction('bayarIuran', data: [
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 6,
                'to_month' => 3,
                'total_amount' => 150000,
                'received_by_name' => $admin->name,
            ])
            ->assertHasActionErrors(['to_month']);

        $this->assertDatabaseCount('dues_records', 0);
    }

    /**
     * Halaman terbuka tanpa formulir: yang tampil hanya arsip nota, dan
     * pembayaran baru dimulai dari tombol di header.
     */
    public function test_the_payment_form_is_behind_a_header_action(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->assertOk()
            ->assertActionExists('bayarIuran');
    }
}
