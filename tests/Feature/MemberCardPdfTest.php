<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCardPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_route_streams_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();

        $response = $this->actingAs($admin)->get(route('members.card', $member));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_card_route_is_behind_auth(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'members.card');

        $this->assertContains('auth', $route->gatherMiddleware());
    }

    /**
     * Regression: the 86×54mm card must never spill onto a second page, even
     * for a member with no photo (placeholder) and the longest name + hanzi +
     * address the layout caps allow.
     */
    public function test_card_fits_on_a_single_page_even_with_maximal_data(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create(['name' => 'Kelompok Sembilan Belas']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'photo_path' => null,
            'name_hanzi' => '好你吗世界欢迎',
            'name_indonesian' => 'Muhammad Abdurrahman Wijayakusuma Hardiansyah Putra',
            'address' => 'Jalan Raya Pasar Minggu Komplek Pertokoan Blok ZZ No. 12345, Jakarta Selatan 99999, DKI Jakarta',
        ]);

        $response = $this->actingAs($admin)->get(route('members.card', $member));

        $response->assertOk();
        $this->assertSame(1, $this->countPdfPages($response->getContent()));
    }

    private function countPdfPages(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }
}
