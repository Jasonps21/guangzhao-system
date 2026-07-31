<?php

namespace Tests\Feature;

use App\Enums\DuesCategory;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Memilih kategori iuran harus mengisi nominal per bulan secara otomatis.
 * Filament meng-cast state select menjadi objek enum, jadi hook-nya wajib
 * menerima enum — bukan string — kalau tidak halaman langsung error.
 */
class MemberDuesCategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_choosing_a_category_fills_the_monthly_amount_on_create(): void
    {
        Livewire::test(CreateMember::class)
            ->fillForm(['dues_category' => DuesCategory::Menengah->value])
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['monthly_dues_amount' => 30000.0]);
    }

    public function test_every_category_has_its_own_suggested_amount(): void
    {
        $expected = [
            DuesCategory::Prasejahtera->value => 0.0,
            DuesCategory::KurangMampu->value => 15000.0,
            DuesCategory::Menengah->value => 30000.0,
            DuesCategory::Mampu->value => 100000.0,
        ];

        foreach ($expected as $category => $amount) {
            Livewire::test(CreateMember::class)
                ->fillForm(['dues_category' => $category])
                ->assertSchemaStateSet(['monthly_dues_amount' => $amount]);
        }
    }

    public function test_changing_the_category_on_an_existing_member_updates_the_amount(): void
    {
        $member = Member::factory()->active()->create([
            'dues_category' => DuesCategory::KurangMampu,
            'monthly_dues_amount' => 15000,
        ]);

        Livewire::test(EditMember::class, ['record' => $member->id])
            ->fillForm(['dues_category' => DuesCategory::Mampu->value])
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['monthly_dues_amount' => 100000.0]);
    }

    public function test_clearing_the_category_leaves_the_amount_untouched(): void
    {
        $member = Member::factory()->active()->create([
            'dues_category' => DuesCategory::Mampu,
            'monthly_dues_amount' => 100000,
        ]);

        Livewire::test(EditMember::class, ['record' => $member->id])
            ->fillForm(['dues_category' => null])
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['monthly_dues_amount' => 100000.0]);
    }
}
