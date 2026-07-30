<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Support\OpeningBalanceImporter;
use App\Support\OpeningBalanceResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceImportTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2026;

    /**
     * Go-live Agustus: tagihan lama dibuat sampai Juli.
     */
    private const THROUGH_MONTH = 7;

    public function test_a_member_who_last_paid_in_january_owes_february_through_july(): void
    {
        $member = $this->member('A-014', 30000);

        $this->import([$this->row(1, 'A-014', 1)]);

        $this->assertMonthsHaveStatus($member, range(1, 1), DuesStatus::Lunas);
        $this->assertMonthsHaveStatus($member, range(2, 7), DuesStatus::BelumBayar);
        $this->assertSame(7, $member->duesRecords()->count());
    }

    public function test_a_member_who_last_paid_in_july_has_no_arrears(): void
    {
        $member = $this->member('A-021', 30000);

        $result = $this->import([$this->row(1, 'A-021', 7)]);

        $this->assertMonthsHaveStatus($member, range(1, 7), DuesStatus::Lunas);
        $this->assertSame(0, $result->arrearsCreated);
        $this->assertSame(0.0, $result->arrearsTotal);
    }

    public function test_a_member_who_prepaid_through_december_is_marked_paid_past_the_due_month(): void
    {
        $member = $this->member('A-033', 30000);

        $result = $this->import([$this->row(1, 'A-033', 12)]);

        $this->assertMonthsHaveStatus($member, range(1, 12), DuesStatus::Lunas);
        $this->assertSame(12, $result->paidCreated);
        $this->assertSame(0, $result->arrearsCreated);
    }

    public function test_a_blank_last_paid_month_means_nothing_was_ever_paid_this_year(): void
    {
        $member = $this->member('A-040', 25000);

        $result = $this->import([$this->row(1, 'A-040', null)]);

        $this->assertMonthsHaveStatus($member, range(1, 7), DuesStatus::BelumBayar);
        $this->assertSame(7, $result->arrearsCreated);
        $this->assertSame(175000.0, $result->arrearsTotal);
    }

    public function test_imported_paid_months_are_marked_as_migrated_so_they_are_distinguishable(): void
    {
        $member = $this->member('A-050', 30000);

        $this->import([$this->row(1, 'A-050', 3)]);

        $record = $member->duesRecords()->where('period_month', 2)->sole();
        $this->assertSame(CollectionMethod::Migrasi, $record->collection_method);
        $this->assertSame('30000.00', $record->amount_paid);
        $this->assertNull($record->recorded_by);
    }

    public function test_month_names_in_indonesian_are_accepted(): void
    {
        $member = $this->member('A-060', 30000);

        $this->import([$this->row(1, 'A-060', 'Maret')]);

        $this->assertMonthsHaveStatus($member, range(1, 3), DuesStatus::Lunas);
        $this->assertMonthsHaveStatus($member, range(4, 7), DuesStatus::BelumBayar);
    }

    public function test_a_member_who_joined_mid_year_is_not_billed_for_earlier_months(): void
    {
        $member = $this->member('A-070', 30000, ['joined_at' => '2026-05-10']);

        $result = $this->import([$this->row(1, 'A-070', null)]);

        // Mei, Juni, Juli saja — bukan Januari.
        $this->assertMonthsHaveStatus($member, range(5, 7), DuesStatus::BelumBayar);
        $this->assertSame(3, $result->arrearsCreated);
        $this->assertSame(0, $member->duesRecords()->where('period_month', '<', 5)->count());
    }

    public function test_members_free_of_dues_never_get_arrears(): void
    {
        $member = $this->member('A-080', 0);

        $result = $this->import([$this->row(1, 'A-080', null)]);

        $this->assertSame(0, $member->duesRecords()->count());
        $this->assertSame(1, $result->skippedFreeOfDues);
        $this->assertSame(0, $result->membersProcessed);
    }

    public function test_an_unknown_member_number_is_reported_instead_of_guessed(): void
    {
        $this->member('A-090', 30000);

        $result = $this->import([$this->row(4, 'A-999', 1)]);

        $this->assertTrue($result->hasIssues());
        $this->assertSame(4, $result->issues[0]['row']);
        $this->assertSame('A-999', $result->issues[0]['member_number']);
        $this->assertDatabaseCount('dues_records', 0);
    }

    public function test_an_unreadable_month_is_reported_instead_of_guessed(): void
    {
        $this->member('A-100', 30000);

        $result = $this->import([$this->row(2, 'A-100', 'entah kapan')]);

        $this->assertTrue($result->hasIssues());
        $this->assertStringContainsString('tidak terbaca', $result->issues[0]['reason']);
        $this->assertDatabaseCount('dues_records', 0);
    }

    public function test_existing_dues_records_are_never_overwritten(): void
    {
        $member = $this->member('A-110', 30000);
        $existing = DuesRecord::factory()->forPeriod(self::YEAR, 3)->create([
            'member_id' => $member->id,
            'status' => DuesStatus::BelumBayar,
        ]);

        // Excel bilang Maret sudah lunas, tapi baris itu sudah ada di sistem.
        $result = $this->import([$this->row(1, 'A-110', 5)]);

        $this->assertSame(DuesStatus::BelumBayar, $existing->refresh()->status);
        $this->assertSame(1, $result->skippedExisting);
    }

    public function test_dry_run_reports_totals_without_writing_anything(): void
    {
        $this->member('A-120', 30000);

        $result = $this->import([$this->row(1, 'A-120', 1)], dryRun: true);

        $this->assertSame(6, $result->arrearsCreated);
        $this->assertSame(180000.0, $result->arrearsTotal);
        $this->assertDatabaseCount('dues_records', 0);
    }

    public function test_arrears_are_listed_per_member_for_reconciliation_against_the_spreadsheet(): void
    {
        $this->member('A-130', 30000);
        $this->member('A-131', 15000);

        $result = $this->import([
            $this->row(1, 'A-130', 5),
            $this->row(2, 'A-131', 6),
        ]);

        $this->assertCount(2, $result->arrearsPerMember);
        $this->assertSame(['A-130', 2, 60000.0], [
            $result->arrearsPerMember[0]['member_number'],
            $result->arrearsPerMember[0]['months'],
            $result->arrearsPerMember[0]['amount'],
        ]);
        $this->assertSame(75000.0, $result->arrearsTotal);
    }

    public function test_per_month_marks_record_each_month_exactly_as_written(): void
    {
        $member = $this->member('B-010', 30000);

        $this->importMatrix([
            $this->matrixRow(2, 'B-010', ['L', 'L', 'B', 'B', 'L', 'B', 'B', '-', '-', '-', '-', '-']),
        ]);

        $this->assertMonthsHaveStatus($member, [1, 2, 5], DuesStatus::Lunas);
        $this->assertMonthsHaveStatus($member, [3, 4, 6, 7], DuesStatus::BelumBayar);
    }

    /**
     * Keunggulan format per bulan: pembayaran yang loncat-loncat bisa dinyatakan
     * apa adanya, tidak dipaksa berurutan.
     */
    public function test_non_contiguous_payments_are_preserved(): void
    {
        $member = $this->member('B-020', 30000);

        $result = $this->importMatrix([
            $this->matrixRow(2, 'B-020', ['L', 'B', 'L', 'B', 'B', 'B', 'B', '-', '-', '-', '-', '-']),
        ]);

        $this->assertMonthsHaveStatus($member, [1, 3], DuesStatus::Lunas);
        $this->assertMonthsHaveStatus($member, [2, 4, 5, 6, 7], DuesStatus::BelumBayar);
        $this->assertSame(5, $result->arrearsCreated);
        $this->assertSame(150000.0, $result->arrearsTotal);
    }

    public function test_months_marked_not_applicable_get_no_dues_record_at_all(): void
    {
        $member = $this->member('B-030', 30000);

        $this->importMatrix([
            $this->matrixRow(2, 'B-030', ['-', '-', '-', '-', 'B', 'B', 'B', '-', '-', '-', '-', '-']),
        ]);

        $this->assertSame(0, $member->duesRecords()->where('period_month', '<', 5)->count());
        $this->assertSame(3, $member->duesRecords()->count());
    }

    public function test_a_month_marked_paid_beyond_the_due_month_records_a_prepayment(): void
    {
        $member = $this->member('B-040', 30000);

        $this->importMatrix([
            $this->matrixRow(2, 'B-040', ['L', 'L', 'L', 'L', 'L', 'L', 'L', 'L', 'L', 'L', 'L', 'L']),
        ]);

        $this->assertMonthsHaveStatus($member, range(1, 12), DuesStatus::Lunas);
    }

    public function test_a_blank_cell_is_rejected_rather_than_assumed_unpaid(): void
    {
        $this->member('B-050', 30000);

        $result = $this->importMatrix([
            $this->matrixRow(3, 'B-050', ['L', '', 'B', 'B', 'B', 'B', 'B', '-', '-', '-', '-', '-']),
        ]);

        $this->assertTrue($result->hasIssues());
        $this->assertStringContainsString('kosong', $result->issues[0]['reason']);
        $this->assertDatabaseCount('dues_records', 0);
    }

    public function test_an_unrecognised_mark_is_rejected(): void
    {
        $this->member('B-060', 30000);

        $result = $this->importMatrix([
            $this->matrixRow(3, 'B-060', ['L', 'sudah', 'B', 'B', 'B', 'B', 'B', '-', '-', '-', '-', '-']),
        ]);

        $this->assertTrue($result->hasIssues());
        $this->assertDatabaseCount('dues_records', 0);
    }

    public function test_the_sheet_name_is_reported_so_the_error_can_be_found(): void
    {
        $result = $this->importMatrix([
            $this->matrixRow(5, 'TIDAK-ADA', array_fill(0, 12, 'B'), sheet: 'Kelompok 3'),
        ]);

        $this->assertSame('Kelompok 3', $result->issues[0]['sheet']);
        $this->assertSame(5, $result->issues[0]['row']);
    }

    /**
     * @param  array<int, string>  $marks  12 tanda, Januari sampai Desember.
     * @return array{sheet: string, row: int, member_number: mixed, months: array<int, mixed>}
     */
    private function matrixRow(int $rowNumber, string $memberNumber, array $marks, string $sheet = 'Kelompok 1'): array
    {
        $months = [];

        foreach ($marks as $offset => $mark) {
            $months[$offset + 1] = $mark;
        }

        return [
            'sheet' => $sheet,
            'row' => $rowNumber,
            'member_number' => $memberNumber,
            'months' => $months,
        ];
    }

    /**
     * @param  array<int, array{sheet: string, row: int, member_number: mixed, months: array<int, mixed>}>  $rows
     */
    private function importMatrix(array $rows, bool $dryRun = false): OpeningBalanceResult
    {
        return app(OpeningBalanceImporter::class)->importMatrix($rows, self::YEAR, $dryRun);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function member(string $number, float $duesAmount, array $attributes = []): Member
    {
        return Member::factory()->active()->create([
            'member_number' => $number,
            'monthly_dues_amount' => $duesAmount,
            // Dikunci: factory mengisi joined_at acak, yang bisa memotong
            // bulan-bulan awal dan membuat tes ini tidak menentu.
            'joined_at' => null,
            ...$attributes,
        ]);
    }

    /**
     * @return array{row: int, member_number: mixed, last_paid_month: mixed}
     */
    private function row(int $rowNumber, string $memberNumber, mixed $lastPaidMonth): array
    {
        return [
            'row' => $rowNumber,
            'member_number' => $memberNumber,
            'last_paid_month' => $lastPaidMonth,
        ];
    }

    /**
     * @param  array<int, array{row: int, member_number: mixed, last_paid_month: mixed}>  $rows
     */
    private function import(array $rows, bool $dryRun = false): OpeningBalanceResult
    {
        return app(OpeningBalanceImporter::class)
            ->import($rows, self::YEAR, self::THROUGH_MONTH, $dryRun);
    }

    /**
     * @param  array<int, int>  $months
     */
    private function assertMonthsHaveStatus(Member $member, array $months, DuesStatus $status): void
    {
        foreach ($months as $month) {
            $record = $member->duesRecords()
                ->where('period_year', self::YEAR)
                ->where('period_month', $month)
                ->first();

            $this->assertNotNull($record, "Tagihan bulan {$month} tidak dibuat.");
            $this->assertSame($status, $record->status, "Status bulan {$month} tidak sesuai.");
        }
    }
}
