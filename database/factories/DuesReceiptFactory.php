<?php

namespace Database\Factories;

use App\Models\DuesReceipt;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuesReceipt>
 */
class DuesReceiptFactory extends Factory
{
    protected $model = DuesReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fromMonth = fake()->numberBetween(1, 10);
        $toMonth = fake()->numberBetween($fromMonth, 12);
        $months = $toMonth - $fromMonth + 1;

        return [
            'member_id' => Member::factory(),
            'period_year' => (int) now()->year,
            'from_month' => $fromMonth,
            'to_month' => $toMonth,
            'months_count' => $months,
            'total_amount' => $months * 15000,
            'issued_at' => now(),
        ];
    }
}
