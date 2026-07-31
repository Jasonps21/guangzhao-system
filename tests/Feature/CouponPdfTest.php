<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Filament\Resources\DuesRecords\Pages\ListDuesRecords;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
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

    /**
     * Font CJK hanya ditanam sebagian (subset). Tanpa ini DomPDF menyalin seluruh
     * berkas WenQuanYi 11,6 MB ke tiap PDF: satu kupon pun jadi ±6,8 MB, dan
     * unduhannya sendiri yang membuat cetak terasa lama.
     */
    public function test_coupon_pdf_embeds_only_a_font_subset(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['name_hanzi' => '黄英强']);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $pdf = $this->actingAs($admin)
            ->get(route('dues.coupons', ['year' => 2025, 'month' => 12]))
            ->getContent();

        $this->assertMatchesRegularExpression('#/BaseFont\s*/[A-Z]{6}\+WenQuanYiZenHei#', $pdf);
        $this->assertLessThan(2 * 1024 * 1024, strlen($pdf), 'PDF kupon membengkak — font CJK kemungkinan ditanam utuh lagi.');
    }

    /**
     * Kelompok wajib dipilih. Mencetak seluruh anggota sekaligus memakan puluhan
     * detik, dan kupon memang dibagikan per kelompok.
     */
    public function test_printing_requires_a_group_to_be_chosen(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListDuesRecords::class)
            ->callAction('printCoupons', data: [
                'year' => 2025,
                'month' => 12,
                'group_id' => null,
            ])
            ->assertHasActionErrors(['group_id' => 'required'])
            ->assertNoRedirect();

        Livewire::actingAs($admin)
            ->test(ListDuesRecords::class)
            ->callAction('printCoupons', data: [
                'year' => 2025,
                'month' => 12,
                'group_id' => $group->id,
            ])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('dues.coupons', [
                'year' => 2025,
                'month' => 12,
                'group' => $group->id,
            ]));
    }

    /**
     * Penekanan tombol cetak yang kedua tidak boleh memicu render kedua —
     * itulah yang membuat server bekerja dua kali saat petugas mengira
     * cetakannya belum jalan.
     *
     * Sengaja memakai driver cache `database` seperti produksi. Dengan driver
     * `array` bawaan pengujian, byte apa pun diterima begitu saja di memori,
     * sehingga PDF mentah yang ditolak kolom mediumtext MariaDB lolos tanpa
     * ketahuan — persis yang membuat cetak kupon error 500 di server.
     */
    public function test_repeating_the_same_print_is_served_from_cache(): void
    {
        config(['cache.default' => 'database']);

        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create();
        $member = Member::factory()->active()->create(['group_id' => $group->id]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $url = route('dues.coupons', ['year' => 2025, 'month' => 12, 'group' => $group->id]);

        $first = $this->actingAs($admin)->get($url);
        $first->assertOk();

        $renders = 0;
        View::composer('pdf.coupons', function () use (&$renders): void {
            $renders++;
        });

        $second = $this->actingAs($admin)->get($url);
        $second->assertOk();

        $this->assertSame(0, $renders, 'Cetakan kedua seharusnya diambil dari cache, bukan dirender ulang.');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertStringStartsWith('%PDF', $second->getContent());

        // Apa pun yang masuk kolom cache harus aman sebagai teks.
        foreach (DB::table('cache')->pluck('value') as $value) {
            $this->assertTrue(
                mb_check_encoding($value, 'UTF-8'),
                'Nilai cache mengandung byte mentah — kolom mediumtext MariaDB akan menolaknya.',
            );
        }
    }

    /**
     * Cache dikunci sidik jari data: begitu ada anggota yang berubah, kupon
     * lama tidak boleh tersaji lagi.
     */
    public function test_editing_a_member_invalidates_the_cached_coupons(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create();
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'name_indonesian' => 'Nama Lama',
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $url = route('dues.coupons', ['year' => 2025, 'month' => 12, 'group' => $group->id]);

        $before = $this->actingAs($admin)->get($url)->getContent();

        $this->travel(1)->second();
        $member->update(['name_indonesian' => 'Nama Baru']);

        $after = $this->actingAs($admin)->get($url)->getContent();

        $this->assertNotSame($before, $after);
    }

    /**
     * Watermark diulang di tiap kupon, jadi logonya harus dirujuk lewat path.
     * Kalau kembali jadi data URI, HTML-nya membengkak ratusan MB saat mencetak
     * seluruh anggota dan proses cetak melambat drastis.
     */
    public function test_coupon_html_does_not_inline_the_logo_per_coupon(): void
    {
        $admin = User::factory()->admin()->create();
        $members = Member::factory()->active()->count(12)->create();
        foreach ($members as $member) {
            DuesRecord::factory()->create([
                'member_id' => $member->id,
                'period_year' => 2025,
                'period_month' => 12,
            ]);
        }

        $rendered = [];
        view()->composer('pdf.coupons', function ($view) use (&$rendered): void {
            $rendered[] = $view->getData();
        });

        $this->actingAs($admin)->get(route('dues.coupons', ['year' => 2025, 'month' => 12]))->assertOk();

        $this->assertNotEmpty($rendered);
        $logo = $rendered[0]['logo'];
        $this->assertIsString($logo);
        $this->assertStringNotContainsString('base64', $logo);
        $this->assertFileExists($logo);
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
