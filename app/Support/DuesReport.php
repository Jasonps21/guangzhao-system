<?php

namespace App\Support;

use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use Illuminate\Support\Collection;

class DuesReport
{
    /**
     * Per-group collected vs. arrears summary for a period. (§J)
     *
     * @return Collection<int, array{group: string, collected: float, arrears: float, paid_count: int, unpaid_count: int}>
     */
    public function perGroup(int $year, int $month): Collection
    {
        return DuesRecord::query()
            ->forPeriod($year, $month)
            ->with('member.group')
            ->get()
            ->groupBy(fn (DuesRecord $r) => $r->member?->group?->name ?? 'Tanpa Kelompok')
            ->map(function (Collection $records, string $groupName): array {
                $paid = $records->where('status', DuesStatus::Lunas);
                $unpaid = $records->where('status', DuesStatus::BelumBayar);

                return [
                    'group' => $groupName,
                    'collected' => (float) $paid->sum('amount_paid'),
                    'arrears' => (float) $unpaid->sum('amount_due'),
                    'paid_count' => $paid->count(),
                    'unpaid_count' => $unpaid->count(),
                ];
            })
            ->sortBy('group')
            ->values();
    }

    /**
     * Arrears (unpaid) records for a period. (§J)
     *
     * @return Collection<int, DuesRecord>
     */
    public function arrears(int $year, int $month): Collection
    {
        return DuesRecord::query()
            ->forPeriod($year, $month)
            ->unpaid()
            ->with('member.group')
            ->get()
            ->sortBy(fn (DuesRecord $r) => [$r->member?->group?->name, $r->member?->member_number])
            ->values();
    }

    /**
     * @return array{collected: float, arrears: float}
     */
    public function totals(int $year, int $month): array
    {
        $records = DuesRecord::query()->forPeriod($year, $month)->get();

        return [
            'collected' => (float) $records->where('status', DuesStatus::Lunas)->sum('amount_paid'),
            'arrears' => (float) $records->where('status', DuesStatus::BelumBayar)->sum('amount_due'),
        ];
    }
}
