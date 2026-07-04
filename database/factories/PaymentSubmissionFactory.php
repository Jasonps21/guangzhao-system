<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Member;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSubmission>
 */
class PaymentSubmissionFactory extends Factory
{
    protected $model = PaymentSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'collector_id' => User::factory()->collector(),
            'period_year' => (int) now()->year,
            'from_month' => 6,
            'to_month' => 12,
            'total_amount' => 210000,
            'status' => SubmissionStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Approved,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'review_notes' => 'Uang setoran tidak sesuai.',
        ]);
    }
}
