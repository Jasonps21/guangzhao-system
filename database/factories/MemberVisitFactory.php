<?php

namespace Database\Factories;

use App\Enums\VisitOutcome;
use App\Models\Member;
use App\Models\MemberVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberVisit>
 */
class MemberVisitFactory extends Factory
{
    protected $model = MemberVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'user_id' => User::factory(),
            'visited_at' => now(),
            'latitude' => fake()->latitude(-5.2, -5.1),
            'longitude' => fake()->longitude(119.4, 119.5),
            'address_snapshot' => fake()->address(),
            'photo_path' => null,
            'outcome' => VisitOutcome::Bertemu,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
