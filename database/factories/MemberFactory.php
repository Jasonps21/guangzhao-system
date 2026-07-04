<?php

namespace Database\Factories;

use App\Enums\DuesCategory;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(DuesCategory::cases());

        return [
            'member_number' => 'GZ-'.fake()->unique()->numberBetween(10000, 99999),
            'name_hanzi' => null,
            'name_pinyin' => null,
            'name_indonesian' => fake()->name(),
            'address' => fake()->optional()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'photo_path' => null,
            'dues_category' => $category,
            'monthly_dues_amount' => $category->defaultAmount(),
            'group_id' => MemberGroup::factory(),
            'no_urut' => fake()->optional()->numberBetween(1, 50),
            'bill_at_home' => fake()->boolean(20),
            'status' => MemberStatus::Aktif,
            'joined_at' => fake()->optional()->dateTimeBetween('-5 years'),
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MemberStatus::Aktif]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => fake()->randomElement([
                MemberStatus::MengundurkanDiri,
                MemberStatus::Pindah,
                MemberStatus::Meninggal,
            ]),
            'status_changed_at' => now(),
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Meninggal,
            'status_changed_at' => now(),
        ]);
    }

    public function inGroup(MemberGroup $group): static
    {
        return $this->state(fn (array $attributes) => ['group_id' => $group->id]);
    }
}
