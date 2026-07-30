<?php

namespace Database\Factories;

use App\Enums\SocialFundDirection;
use App\Enums\SocialFundForm;
use App\Models\SocialFundRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialFundRecord>
 */
class SocialFundRecordFactory extends Factory
{
    protected $model = SocialFundRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direction' => SocialFundDirection::Masuk,
            'fund_form' => SocialFundForm::Uang,
            'party_name' => fake()->name(),
            'party_address' => fake()->address(),
            'amount' => fake()->numberBetween(1, 50) * 100000,
            'item_description' => null,
            'purpose' => 'Dana sosial',
            'transacted_at' => fake()->dateTimeBetween('-1 year'),
            'notes' => null,
        ];
    }

    public function incoming(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => SocialFundDirection::Masuk]);
    }

    public function outgoing(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => SocialFundDirection::Keluar]);
    }

    /**
     * Sumbangan berupa barang — nominal jadi taksiran dan boleh kosong.
     */
    public function inGoods(?string $description = null): static
    {
        return $this->state(fn (array $attributes) => [
            'fund_form' => SocialFundForm::Barang,
            'amount' => null,
            'item_description' => $description ?? '10 dus air mineral',
        ]);
    }
}
