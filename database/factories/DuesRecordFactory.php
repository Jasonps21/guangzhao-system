<?php

namespace Database\Factories;

use App\Enums\DuesStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuesRecord>
 */
class DuesRecordFactory extends Factory
{
    protected $model = DuesRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'amount_due' => 30000,
            'amount_paid' => null,
            'status' => DuesStatus::BelumBayar,
            'paid_at' => null,
            'recorded_by' => null,
            'collection_method' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DuesStatus::Lunas,
            'amount_paid' => $attributes['amount_due'] ?? 30000,
            'paid_at' => now(),
        ]);
    }

    public function forPeriod(int $year, int $month): static
    {
        return $this->state(fn (array $attributes) => [
            'period_year' => $year,
            'period_month' => $month,
        ]);
    }
}
