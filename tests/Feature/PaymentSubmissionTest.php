<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\PaymentSubmissions\Pages\ListPaymentSubmissions;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\PaymentService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_range_creates_a_pending_submission_with_total(): void
    {
        $collector = User::factory()->collector()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);

        $this->assertNotNull($submission);
        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertSame('140000.00', $submission->total_amount);
        $this->assertSame(7, $member->duesRecords()->where('status', DuesStatus::MenungguPersetujuan)->count());
        $this->assertSame(0, $member->duesRecords()->where('status', DuesStatus::Lunas)->count());
    }

    public function test_submit_range_skips_already_paid_months(): void
    {
        $collector = User::factory()->collector()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);
        DuesRecord::factory()->forPeriod(2026, 6)->paid()->create([
            'member_id' => $member->id,
            'amount_due' => 20000,
        ]);

        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 8, $collector);

        // Juni sudah lunas → hanya Juli & Agustus yang menunggu.
        $this->assertSame('40000.00', $submission->total_amount);
        $this->assertSame(2, $submission->duesRecords()->count());
    }

    public function test_approving_a_submission_marks_all_months_paid(): void
    {
        $collector = User::factory()->collector()->create();
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);
        app(PaymentService::class)->approveSubmission($submission->refresh(), $admin);

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Approved, $submission->status);
        $this->assertSame($admin->id, $submission->reviewed_by);

        $paid = $member->duesRecords()->where('status', DuesStatus::Lunas)->get();
        $this->assertCount(7, $paid);
        // Kolektor pencatat tetap dipertahankan, metode tetap lapangan.
        $this->assertTrue($paid->every(fn (DuesRecord $r) => $r->recorded_by === $collector->id));
        $this->assertTrue($paid->every(fn (DuesRecord $r) => $r->collection_method === CollectionMethod::Lapangan));
        $this->assertTrue($paid->every(fn (DuesRecord $r) => $r->paid_at !== null));
    }

    public function test_rejecting_a_submission_returns_months_to_unpaid(): void
    {
        $collector = User::factory()->collector()->create();
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);
        app(PaymentService::class)->rejectSubmission($submission->refresh(), $admin, 'Uang belum disetor.');

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Rejected, $submission->status);
        $this->assertSame('Uang belum disetor.', $submission->review_notes);

        $records = $member->duesRecords()->get();
        $this->assertCount(7, $records);
        $this->assertTrue($records->every(fn (DuesRecord $r) => $r->status === DuesStatus::BelumBayar));
        $this->assertTrue($records->every(fn (DuesRecord $r) => $r->submission_id === null));
        $this->assertTrue($records->every(fn (DuesRecord $r) => $r->amount_paid === null));
    }

    public function test_approving_an_already_reviewed_submission_is_a_noop(): void
    {
        $admin = User::factory()->admin()->create();
        $submission = PaymentSubmission::factory()->approved()->create();

        $this->assertFalse(app(PaymentService::class)->approveSubmission($submission, $admin));
    }

    public function test_admin_can_approve_a_submission_from_the_panel(): void
    {
        $collector = User::factory()->collector()->create();
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);
        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);

        Livewire::actingAs($admin)
            ->test(ListPaymentSubmissions::class)
            ->callAction(TestAction::make('approve')->table($submission))
            ->assertNotified();

        $this->assertSame(SubmissionStatus::Approved, $submission->refresh()->status);
        $this->assertSame(7, $member->duesRecords()->where('status', DuesStatus::Lunas)->count());
    }

    public function test_admin_can_reject_a_submission_from_the_panel(): void
    {
        $collector = User::factory()->collector()->create();
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);
        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);

        Livewire::actingAs($admin)
            ->test(ListPaymentSubmissions::class)
            ->callAction(TestAction::make('reject')->table($submission), [
                'review_notes' => 'Nominal tidak cocok.',
            ])
            ->assertNotified();

        $this->assertSame(SubmissionStatus::Rejected, $submission->refresh()->status);
        $this->assertSame(7, $member->duesRecords()->where('status', DuesStatus::BelumBayar)->count());
    }
}
