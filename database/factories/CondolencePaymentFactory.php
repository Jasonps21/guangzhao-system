<?php

namespace Database\Factories;

use App\Models\CondolencePayment;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CondolencePayment>
 */
class CondolencePaymentFactory extends Factory
{
    protected $model = CondolencePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'recipient_name' => fake()->name(),
            'recipient_member_number' => 'GZ-'.fake()->numberBetween(10000, 99999),
            'deceased_name_hanzi' => null,
            'amount' => 1000000,
            'escort_name' => fake()->optional()->name(),
            'given_at' => fake()->dateTimeBetween('-1 year'),
            'notes' => null,
        ];
    }

    /**
     * Nota untuk penerima non-anggota (nama diketik manual).
     */
    public function withoutMember(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => null,
            'recipient_member_number' => null,
        ]);
    }

    public function forMember(Member $member): static
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $member->id,
            'recipient_name' => $member->displayName(),
            'recipient_member_number' => $member->member_number,
        ]);
    }
}
