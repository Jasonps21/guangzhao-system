<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RelationshipType;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\RelationManagers\FamilyRelationshipsRelationManager;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_relationship_auto_creates_the_reciprocal(): void
    {
        $andi = Member::factory()->create(['gender' => Gender::Male]);
        $junita = Member::factory()->create(['gender' => Gender::Female]);

        $andi->familyRelationships()->create([
            'related_member_id' => $junita->id,
            'relationship_type' => RelationshipType::Spouse,
        ]);

        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $junita->id,
            'related_member_id' => $andi->id,
            'relationship_type' => 'spouse',
        ]);

        $this->assertSame(2, MemberRelationship::query()->count());
    }

    public function test_child_relationship_reciprocates_as_parent(): void
    {
        $parent = Member::factory()->create();
        $child = Member::factory()->create();

        $parent->familyRelationships()->create([
            'related_member_id' => $child->id,
            'relationship_type' => RelationshipType::Child,
        ]);

        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $child->id,
            'related_member_id' => $parent->id,
            'relationship_type' => 'parent',
        ]);
    }

    public function test_grandparent_relationship_reciprocates_as_grandchild(): void
    {
        $grandparent = Member::factory()->create();
        $grandchild = Member::factory()->create();

        $grandparent->familyRelationships()->create([
            'related_member_id' => $grandchild->id,
            'relationship_type' => RelationshipType::Grandchild,
        ]);

        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $grandchild->id,
            'related_member_id' => $grandparent->id,
            'relationship_type' => 'grandparent',
        ]);
    }

    public function test_deleting_a_relationship_removes_the_reciprocal(): void
    {
        $andi = Member::factory()->create();
        $junita = Member::factory()->create();

        $relationship = $andi->familyRelationships()->create([
            'related_member_id' => $junita->id,
            'relationship_type' => RelationshipType::Spouse,
        ]);

        $relationship->delete();

        $this->assertSame(0, MemberRelationship::query()->count());
    }

    public function test_updating_the_type_updates_the_reciprocal(): void
    {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $relationship = $a->familyRelationships()->create([
            'related_member_id' => $b->id,
            'relationship_type' => RelationshipType::Child,
        ]);

        $relationship->update(['relationship_type' => RelationshipType::Parent]);

        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $b->id,
            'related_member_id' => $a->id,
            'relationship_type' => 'child',
        ]);
    }

    public function test_label_reflects_related_member_gender(): void
    {
        $andi = Member::factory()->create(['gender' => Gender::Male]);
        $junita = Member::factory()->create(['gender' => Gender::Female]);

        $andiToJunita = $andi->familyRelationships()->create([
            'related_member_id' => $junita->id,
            'relationship_type' => RelationshipType::Spouse,
        ]);

        $junitaToAndi = $junita->familyRelationships()->first();

        $this->assertSame('Istri', $andiToJunita->label());
        $this->assertSame('Suami', $junitaToAndi->label());
    }

    public function test_label_falls_back_to_neutral_when_gender_missing(): void
    {
        $a = Member::factory()->create(['gender' => null]);
        $b = Member::factory()->create(['gender' => null]);

        $relationship = $a->familyRelationships()->create([
            'related_member_id' => $b->id,
            'relationship_type' => RelationshipType::Spouse,
        ]);

        $this->assertSame('Pasangan', $relationship->label());
    }

    public function test_relation_manager_creates_both_sides_of_the_relationship(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $andi = Member::factory()->create();
        $junita = Member::factory()->create();

        Livewire::test(FamilyRelationshipsRelationManager::class, [
            'ownerRecord' => $andi,
            'pageClass' => EditMember::class,
        ])
            ->callAction(TestAction::make('create')->table(), [
                'relationship_type' => RelationshipType::Spouse->value,
                'related_member_id' => $junita->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $andi->id,
            'related_member_id' => $junita->id,
            'relationship_type' => 'spouse',
        ]);

        $this->assertDatabaseHas('member_relationships', [
            'member_id' => $junita->id,
            'related_member_id' => $andi->id,
            'relationship_type' => 'spouse',
        ]);
    }
}
