<?php

namespace Database\Factories;

use App\Enums\GroupBasis;
use App\Models\MemberGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberGroup>
 */
class MemberGroupFactory extends Factory
{
    protected $model = MemberGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kelompok '.fake()->unique()->numberBetween(1, 999).' — '.fake()->city(),
            'basis' => fake()->randomElement(GroupBasis::cases()),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
