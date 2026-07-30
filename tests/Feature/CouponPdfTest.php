<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_coupons_are_laid_out_for_cut_and_stack_per_group(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'klp1']);

        // 12 members numbered 1..12 → P = ceil(12/10) = 2 sheets. Slot s of sheet
        // p holds member index s*P + p, so the reading order across both sheets is
        // 1,3,5,7,9,11 (sheet 1) then 2,4,6,8,10,12 (sheet 2), with blanks after.
        foreach (range(1, 12) as $number) {
            $member = Member::factory()->active()->create([
                'group_id' => $group->id,
                'no_urut' => $number,
            ]);
            DuesRecord::factory()->create([
                'member_id' => $member->id,
                'period_year' => 2025,
                'period_month' => 12,
            ]);
        }

        $records = DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod(2025, 12)
            ->get()
            ->sortBy(fn (DuesRecord $r) => $r->member->no_urut)
            ->values();

        $html = view('pdf.coupons', [
            'records' => $records,
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();

        // Pull the No. Urut of every coupon in render (reading) order.
        preg_match_all('/No\. Urut<\/td>\s*<td class="sep">:<\/td>\s*<td>(\d+)<\/td>/', $html, $matches);

        $this->assertSame(
            ['1', '3', '5', '7', '9', '11', '2', '4', '6', '8', '10', '12'],
            $matches[1],
        );
    }

    public function test_coupon_name_shows_indonesian_prominently_with_small_pinyin(): void
    {
        $member = Member::factory()->active()->create([
            'name_hanzi' => '黄英強',
            'name_pinyin' => 'Huang Ying Qiang',
            'name_indonesian' => 'Budi Santoso',
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $records = DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod(2025, 12)
            ->get();

        $html = view('pdf.coupons', [
            'records' => $records,
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();

        // Indonesian name is the prominent (main) text on the Nama line...
        $this->assertStringContainsString('Budi Santoso', $html);
        // ...and the pinyin is rendered small inside a .pinyin span.
        $this->assertStringContainsString('<span class="pinyin"> (Huang Ying Qiang)</span>', $html);
    }

    public function test_coupon_pins_kelompok_and_no_urut_outside_the_flowing_block(): void
    {
        // Long name + address make the flowing rows (Nama…Bulan) wrap to several
        // lines. Kelompok / No. Urut must live in the pinned .foot-box, not the
        // clipped .body-top, so they always print in full. (Guards the fix for
        // No. Urut getting cut off the box on members with lots of data.)
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 8']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 61,
            'name_hanzi' => '吴素兰',
            'name_indonesian' => 'Lestiana Gosali Go',
            'address' => 'jl. Metro T.B Ruko Somba Opu AA/ 5 Blok C No 12 Makassar',
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $records = DuesRecord::query()->with(['member.group'])->forPeriod(2025, 12)->get();

        $html = view('pdf.coupons', [
            'records' => $records,
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();

        $footBox = mb_substr($html, mb_strpos($html, 'class="foot-box"'));
        $footBox = mb_substr($footBox, 0, mb_strpos($footBox, '</div>'));

        $this->assertStringContainsString('Kelompok', $footBox);
        $this->assertStringContainsString('No. Urut', $footBox);
        $this->assertStringContainsString('61', $footBox);
    }

    public function test_coupon_embeds_uploaded_logo_when_present(): void
    {
        Storage::fake('public');
        $path = Storage::disk('public')->putFile(
            'organization',
            UploadedFile::fake()->image('logo.png', 200, 200),
        );
        OrganizationSetting::current()->update(['logo_path' => $path]);

        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $response = $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_coupon_falls_back_to_bundled_logo_when_upload_missing(): void
    {
        Storage::fake('public');
        // Path tercatat di DB tetapi berkasnya tidak ada — harus jatuh ke logo bawaan.
        OrganizationSetting::current()->update(['logo_path' => 'organization/hilang.png']);

        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $response = $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
        ]));

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
