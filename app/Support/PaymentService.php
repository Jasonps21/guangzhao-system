<?php

namespace App\Support;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\SubmissionStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\PaymentSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Mark a single dues record paid in full. (§6.2 / §6.3)
     */
    public function pay(DuesRecord $record, User $by, CollectionMethod $method): bool
    {
        return $record->markPaid($by, $method);
    }

    /**
     * Pay a contiguous range of months for one member in a single transaction.
     * Creates any missing dues rows using the member's current monthly amount. (§6.4)
     *
     * @return int Number of records marked paid.
     */
    public function payRange(Member $member, int $year, int $fromMonth, int $toMonth, User $by, CollectionMethod $method): int
    {
        [$fromMonth, $toMonth] = [min($fromMonth, $toMonth), max($fromMonth, $toMonth)];
        $paidAt = CarbonImmutable::now();

        return DB::transaction(function () use ($member, $year, $fromMonth, $toMonth, $by, $method, $paidAt): int {
            $count = 0;

            for ($month = $fromMonth; $month <= $toMonth; $month++) {
                $record = $member->duesRecords()->firstOrCreate(
                    ['period_year' => $year, 'period_month' => $month],
                    ['amount_due' => $member->monthly_dues_amount, 'status' => DuesStatus::BelumBayar],
                );

                if ($record->markPaid($by, $method, $paidAt)) {
                    $count++;
                }
            }

            return $count;
        });
    }

    /**
     * Catat setoran kolektor untuk beberapa bulan sekaligus sebagai MENUNGGU
     * PERSETUJUAN admin — belum dihitung lunas sampai uangnya diverifikasi. (§6.4)
     *
     * Tagihan yang sudah lunas / sudah menunggu dilewati; baris bulan yang belum
     * ada dibuat otomatis memakai iuran bulanan anggota saat ini.
     *
     * @return PaymentSubmission|null Setoran yang dibuat, atau null bila tak ada bulan yang bisa diajukan.
     */
    public function submitRange(Member $member, int $year, int $fromMonth, int $toMonth, User $collector): ?PaymentSubmission
    {
        [$fromMonth, $toMonth] = [min($fromMonth, $toMonth), max($fromMonth, $toMonth)];

        return DB::transaction(function () use ($member, $year, $fromMonth, $toMonth, $collector): ?PaymentSubmission {
            $submission = PaymentSubmission::create([
                'member_id' => $member->getKey(),
                'collector_id' => $collector->getKey(),
                'period_year' => $year,
                'from_month' => $fromMonth,
                'to_month' => $toMonth,
                'total_amount' => 0,
                'status' => SubmissionStatus::Pending,
            ]);

            $total = 0.0;

            for ($month = $fromMonth; $month <= $toMonth; $month++) {
                $record = $member->duesRecords()->firstOrCreate(
                    ['period_year' => $year, 'period_month' => $month],
                    ['amount_due' => $member->monthly_dues_amount, 'status' => DuesStatus::BelumBayar],
                );

                if ($record->markPendingApproval($collector, $submission)) {
                    $total += (float) $record->amount_due;
                }
            }

            if ($submission->duesRecords()->count() === 0) {
                $submission->delete();

                return null;
            }

            $submission->update(['total_amount' => $total]);

            return $submission;
        });
    }

    /**
     * Admin menyetujui setoran: seluruh bulan menunggu menjadi LUNAS. (§6.4)
     */
    public function approveSubmission(PaymentSubmission $submission, User $by): bool
    {
        if (! $submission->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($submission, $by): bool {
            $paidAt = CarbonImmutable::now();

            foreach ($submission->duesRecords as $record) {
                $record->confirmPaid($paidAt);
            }

            $submission->approve($by);

            return true;
        });
    }

    /**
     * Admin menolak setoran: seluruh bulan kembali BELUM BAYAR. (§6.4)
     */
    public function rejectSubmission(PaymentSubmission $submission, User $by, ?string $notes = null): bool
    {
        if (! $submission->isPending()) {
            return false;
        }

        return DB::transaction(function () use ($submission, $by, $notes): bool {
            foreach ($submission->duesRecords as $record) {
                $record->releaseToUnpaid();
            }

            $submission->reject($by, $notes);

            return true;
        });
    }
}
