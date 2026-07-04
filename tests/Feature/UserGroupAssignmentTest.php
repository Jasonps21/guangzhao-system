<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserGroupAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_selector_is_visible_when_editing_a_collector(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $collector = User::factory()->collector()->create();

        Livewire::test(EditUser::class, ['record' => $collector->id])
            ->assertFormFieldIsVisible('groups');
    }

    public function test_collector_groups_can_be_saved_from_the_edit_form(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();

        Livewire::test(EditUser::class, ['record' => $collector->id])
            ->fillForm(['groups' => [$group->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($collector->groups()->whereKey($group->id)->exists());
    }

    public function test_group_selector_is_hidden_for_non_collectors(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $admin = User::factory()->admin()->create();

        Livewire::test(EditUser::class, ['record' => $admin->id])
            ->assertFormFieldIsHidden('groups');
    }
}
