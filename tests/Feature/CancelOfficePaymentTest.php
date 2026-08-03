<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Filament\Pages\PembayaranKantor;
use App\Models\DuesReceipt;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\User;
use App\Support\PaymentService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Tests\TestCase;

/**
 * Menghapus nota pembayaran kantor sekaligus membatalkan pembayarannya, jadi
 * bulan yang dilunasi kembali menjadi tagihan dan uangnya keluar dari laporan.
 * Karena ini menghapus catatan uang, tombolnya hanya untuk Super Admin.
 */
class CancelOfficePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_a_receipt_reverts_its_months_to_unpaid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 3, $admin, CollectionMethod::Admin,
        );

        $reverted = app(PaymentService::class)->cancelReceipt($receipt, $admin);

        $this->assertSame(3, $reverted);
        $this->assertSame(0, DuesReceipt::count());

        $records = DuesRecord::where('member_id', $member->id)->get();
        $this->assertCount(3, $records);

        foreach ($records as $record) {
            $this->assertSame(DuesStatus::BelumBayar, $record->status);
            $this->assertNull($record->amount_paid);
            $this->assertNull($record->paid_at);
            $this->assertNull($record->receipt_id);
        }
    }

    public function test_it_leaves_months_paid_through_another_channel_untouched(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        // Februari sudah dilunasi kolektor lebih dulu, di luar nota kantor.
        $february = DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2026,
            'period_month' => 2,
            'amount_due' => 25000,
        ]);
        $february->markPaid($admin, CollectionMethod::Lapangan);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 3, $admin, CollectionMethod::Admin,
        );

        $this->assertSame(2, $receipt->months_count);

        app(PaymentService::class)->cancelReceipt($receipt, $admin);

        $february->refresh();

        $this->assertSame(DuesStatus::Lunas, $february->status);
        $this->assertSame(CollectionMethod::Lapangan, $february->collection_method);
    }

    public function test_it_still_cancels_a_receipt_issued_before_the_link_column_existed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 3, $admin, CollectionMethod::Admin,
        );

        // Nota lama tidak punya tautan; pembatalannya harus jatuh ke pencocokan
        // periode + petugas + tanggal, sama seperti backfill di migrasi.
        DuesRecord::where('receipt_id', $receipt->id)->update(['receipt_id' => null]);

        $reverted = app(PaymentService::class)->cancelReceipt($receipt->fresh(), $admin);

        $this->assertSame(3, $reverted);
        $this->assertSame(0, DuesRecord::where('status', DuesStatus::Lunas)->count());
    }

    public function test_it_records_who_cancelled_the_receipt(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 2, $admin, CollectionMethod::Admin,
        );
        $number = $receipt->receipt_number;

        app(PaymentService::class)->cancelReceipt($receipt, $admin);

        $entry = ActivityLog::where('description', 'Nota pembayaran kantor dibatalkan')->sole();

        $this->assertSame($admin->id, $entry->causer_id);
        $this->assertSame($number, $entry->getExtraProperty('receipt_number'));
        $this->assertSame(2, $entry->getExtraProperty('months_reverted'));
        $this->assertSame($member->member_number, $entry->getExtraProperty('member_number'));
    }

    public function test_the_delete_button_is_visible_to_a_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 2, $superAdmin, CollectionMethod::Admin,
        );

        Livewire::actingAs($superAdmin)
            ->test(PembayaranKantor::class)
            ->assertActionVisible(TestAction::make('cancelReceipt')->table($receipt));
    }

    public function test_the_delete_button_is_hidden_from_a_plain_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 2, $admin, CollectionMethod::Admin,
        );

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->assertActionHidden(TestAction::make('cancelReceipt')->table($receipt));
    }

    public function test_a_super_admin_can_cancel_a_receipt_from_the_table(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 4, $superAdmin, CollectionMethod::Admin,
        );

        Livewire::actingAs($superAdmin)
            ->test(PembayaranKantor::class)
            ->callAction(TestAction::make('cancelReceipt')->table($receipt))
            ->assertNotified();

        $this->assertSame(0, DuesReceipt::count());
        $this->assertSame(0, DuesRecord::where('status', DuesStatus::Lunas)->count());
    }

    public function test_a_plain_admin_cannot_cancel_a_receipt_from_the_table(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member, 2026, 1, 4, $admin, CollectionMethod::Admin,
        );

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->assertActionHidden(TestAction::make('cancelReceipt')->table($receipt))
            ->assertActionVisible(TestAction::make('print')->table($receipt));

        // Admin biasa tetap boleh mencetak ulang, tetapi notanya harus utuh.
        $this->assertSame(1, DuesReceipt::count());
        $this->assertSame(4, DuesRecord::where('status', DuesStatus::Lunas)->count());
    }
}
