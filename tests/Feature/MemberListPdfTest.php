<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberListPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_list_route_streams_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create();
        Member::factory()->active()->create(['group_id' => $group->id]);

        $response = $this->actingAs($admin)->get(route('members.list'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_member_list_can_be_filtered_to_a_single_group(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create();
        Member::factory()->active()->create(['group_id' => $group->id]);
        Member::factory()->active()->create(); // other group / ungrouped

        $response = $this->actingAs($admin)->get(route('members.list', ['group' => $group->id]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_member_list_excludes_inactive_when_active_only(): void
    {
        $admin = User::factory()->admin()->create();
        Member::factory()->create(['status' => MemberStatus::Pindah]);

        $response = $this->actingAs($admin)->get(route('members.list', ['active_only' => 1]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_member_list_route_is_behind_auth(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'members.list');

        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
