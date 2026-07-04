<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\User;
use App\Support\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_record_records_who_and_how(): void
    {
        $admin = User::factory()->admin()->create();
        $record = DuesRecord::factory()->create(['amount_due' => 30000]);

        app(PaymentService::class)->pay($record, $admin, CollectionMethod::Admin);

        $record->refresh();
        $this->assertSame(DuesStatus::Lunas, $record->status);
        $this->assertSame('30000.00', $record->amount_paid);
        $this->assertSame($admin->id, $record->recorded_by);
        $this->assertSame(CollectionMethod::Admin, $record->collection_method);
        $this->assertNotNull($record->paid_at);
    }

    public function test_paying_an_already_paid_record_is_a_noop(): void
    {
        $admin = User::factory()->admin()->create();
        $record = DuesRecord::factory()->paid()->create();

        $result = app(PaymentService::class)->pay($record, $admin, CollectionMethod::Admin);

        $this->assertFalse($result);
    }

    public function test_pay_range_marks_multiple_months_and_creates_missing_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $count = app(PaymentService::class)->payRange($member, 2026, 1, 12, $admin, CollectionMethod::Admin);

        $this->assertSame(12, $count);
        $this->assertSame(12, $member->duesRecords()->where('status', DuesStatus::Lunas)->count());
        $this->assertDatabaseHas('dues_records', [
            'member_id' => $member->id,
            'period_year' => 2026,
            'period_month' => 7,
            'amount_due' => 20000.00,
            'status' => DuesStatus::Lunas->value,
        ]);
    }

    public function test_collector_payment_is_marked_as_field_collection(): void
    {
        $collector = User::factory()->collector()->create();
        $record = DuesRecord::factory()->create();

        app(PaymentService::class)->pay($record, $collector, CollectionMethod::Lapangan);

        $this->assertSame(CollectionMethod::Lapangan, $record->refresh()->collection_method);
    }
}
