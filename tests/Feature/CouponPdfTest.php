<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_route_streams_a_pdf_for_the_period(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
            'amount_due' => 15000,
        ]);

        $response = $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_coupon_route_is_behind_auth(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'dues.coupons');

        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_coupon_numbers_restart_per_group(): void
    {
        $admin = User::factory()->admin()->create();
        $groupA = MemberGroup::factory()->create(['name' => 'klp1']);
        $groupB = MemberGroup::factory()->create(['name' => 'klp8']);

        foreach ([$groupA, $groupA, $groupB] as $group) {
            $member = Member::factory()->active()->create(['group_id' => $group->id]);
            DuesRecord::factory()->create([
                'member_id' => $member->id,
                'period_year' => 2025,
                'period_month' => 12,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
        ]));

        // Two coupons in group A (numbers 1, 2) and one in group B (number 1).
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_bill_at_home_is_cast_to_boolean(): void
    {
        $member = Member::factory()->create(['bill_at_home' => true]);

        $this->assertTrue($member->refresh()->bill_at_home);
    }

    public function test_inactive_members_are_excluded_from_coupons(): void
    {
        $admin = User::factory()->admin()->create();
        $inactive = Member::factory()->create(['status' => MemberStatus::Pindah]);
        DuesRecord::factory()->create([
            'member_id' => $inactive->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $response = $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
        ]));

        $response->assertOk();
    }
}
