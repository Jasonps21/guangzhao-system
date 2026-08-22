<?php

namespace App\Support;

use App\Enums\DuesStatus;
use App\Models\Member;
use Illuminate\Support\Carbon;
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
        $billed = 0.0;

        /*
         * Tagihan per anggota tidak dicatat satu per satu: sekali generate
         * menyentuh ~194 anggota dan akan menenggelamkan log audit. Yang
         * tercatat adalah satu ringkasan di bawah.
         */
        activity()->withoutLogs(function () use ($year, $month, &$created, &$billed): void {
            Member::query()
                ->active()
                ->whereNotNull('monthly_dues_amount')
                ->chunkById(200, function ($members) use ($year, $month, &$created, &$billed): void {
                    foreach ($members as $member) {
                        DB::transaction(function () use ($member, $year, $month, &$created, &$billed): void {
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
                                $billed += (float) $record->amount_due;
                            }
                        });
                    }
                });
        });

        if ($created > 0) {
            app(ActivityRecorder::class)->bulk('Generate tagihan iuran', [
                'periode' => Carbon::create($year, $month, 1)->translatedFormat('F Y'),
                'jumlah_tagihan' => $created,
                'total_tagihan' => $billed,
            ]);
        }

        return $created;
    }
}
