<?php

namespace App\Support;

use App\Enums\DuesStatus;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class DuesGenerator
{
    /**
     * Generate "belum_bayar" dues records for every active member for the given period.
     *
     * Idempotent: the unique (member_id, period_year, period_month) constraint plus
     * firstOrCreate make it safe to run repeatedly. (§6.1)
     *
     * @return int Number of newly-created records.
     */
    public function generate(int $year, int $month): int
    {
        $created = 0;

        Member::query()
            ->active()
            ->whereNotNull('monthly_dues_amount')
            ->chunkById(200, function ($members) use ($year, $month, &$created): void {
                foreach ($members as $member) {
                    DB::transaction(function () use ($member, $year, $month, &$created): void {
                        $record = $member->duesRecords()->firstOrCreate(
                            [
                                'period_year' => $year,
                                'period_month' => $month,
                            ],
                            [
                                'amount_due' => $member->monthly_dues_amount,
                                'status' => DuesStatus::BelumBayar,
                            ],
                        );

                        if ($record->wasRecentlyCreated) {
                            $created++;
                        }
                    });
                }
            });

        return $created;
    }
}
