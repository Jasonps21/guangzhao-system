<?php

namespace Tests\Feature;

use App\Enums\DuesStatus;
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
     * Perbaikan data langsung di database tidak menyentuh updated_at. Sidik
     * jari yang hanya membaca waktu ubah akan menyajikan kupon lama — nomor
     * urut yang sudah dibetulkan tetap tercetak dengan angka kembarnya.
     */
    public function test_a_change_that_leaves_updated_at_untouched_still_invalidates_the_cache(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create();
        $member = Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 56]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $url = route('dues.coupons', ['year' => 2025, 'month' => 12, 'group' => $group->id]);

        $this->actingAs($admin)->get($url)->assertOk();

        DB::table('members')->where('id', $member->id)->update(['no_urut' => 57]);

        $renderedNoUrut = null;
        View::composer('pdf.coupons', function ($view) use (&$renderedNoUrut): void {
            $renderedNoUrut = $view->getData()['records']->first()->member->no_urut;
        });

        $this->actingAs($admin)->get($url)->assertOk();

        $this->assertSame(57, $renderedNoUrut, 'Kupon seharusnya dirender ulang dengan no. urut yang baru.');
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

    /**
     * Slot yang melewati jumlah anggota tidak boleh tercetak polos. Lembarnya
     * tetap keluar dari printer entah kotaknya diisi atau tidak, jadi mengisinya
     * dengan kupon siap tulis tidak memakan kertas tambahan sama sekali —
     * bedanya hanya hasil potongannya berguna, bukan jadi kertas buangan.
     */
    public function test_leftover_slots_are_filled_with_ready_to_write_spare_coupons(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 5']);

        // 12 anggota → P = ceil(12/10) = 2 lembar = 20 slot, jadi 8 slot sisa.
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

        $html = $this->renderCouponsHtml();

        $this->assertStringNotContainsString(
            'empty-cell',
            $html,
            'Masih ada kotak polos — slot sisa belum jadi kupon cadangan.',
        );
        $this->assertSame(
            20,
            substr_count($html, 'class="inner"'),
            'Tiap slot harus berisi kupon utuh: 12 asli + 8 cadangan.',
        );
        // Dihitung dari atribut class-nya, bukan dari 'fill-number' saja —
        // deklarasi CSS-nya sendiri mengandung kata itu dan ikut terhitung.
        $this->assertSame(
            8,
            substr_count($html, 'class="fill fill-number"'),
            'Delapan slot sisa harus jadi delapan kupon cadangan.',
        );
    }

    /**
     * Bulan dan Kelompok sudah pasti diketahui saat mencetak, jadi keduanya
     * ikut tercetak di kupon cadangan supaya petugas cukup menulis empat hal.
     */
    public function test_spare_coupons_carry_the_period_and_the_group(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 5']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 1,
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        $html = $this->renderCouponsHtml();

        // 1 kupon asli + 9 cadangan, semuanya membawa periode dan kelompoknya.
        $this->assertSame(10, substr_count($html, 'Desember 2025'));
        $this->assertSame(10, substr_count($html, 'Kelompok 5'));
        $this->assertSame(9, substr_count($html, 'class="fill fill-number"'));
    }

    /**
     * Data anggota tidak boleh menular ke kotak sebelahnya saat slot sisa
     * dirender — kupon cadangan harus benar-benar kosong pada empat baris itu.
     */
    public function test_spare_coupons_leave_the_member_fields_blank(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 5']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 7,
            'name_hanzi' => null,
            'name_pinyin' => null,
            'name_indonesian' => 'Budi Santoso',
            'address' => 'Jl. Somba Opu 12',
        ]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
            'amount_due' => 15000,
        ]);

        $html = $this->renderCouponsHtml();

        $this->assertSame(1, substr_count($html, 'Budi Santoso'));
        $this->assertSame(1, substr_count($html, 'Jl. Somba Opu 12'));
        $this->assertSame(1, substr_count($html, 'Rp.15.000,-'));
        $this->assertSame(9, substr_count($html, 'class="fill fill-amount"'));
        $this->assertSame(9, substr_count($html, 'class="fill fill-number"'));
    }

    /**
     * Menambah lembar hanya demi menghasilkan cadangan justru memboroskan
     * kertas — persis keberatan yang membuat fitur ini dibuat.
     */
    public function test_a_group_that_fills_the_sheet_gets_no_spare_coupons(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 2']);

        foreach (range(1, 10) as $number) {
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

        $html = $this->renderCouponsHtml();

        $this->assertSame(10, substr_count($html, 'class="inner"'));
        $this->assertStringNotContainsString('class="fill fill-number"', $html);
        $this->assertStringNotContainsString('page-break-after', $html);
    }

    /**
     * HTML kupon periode Desember 2025, diurutkan seperti PdfController.
     */
    private function renderCouponsHtml(): string
    {
        $records = DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod(2025, 12)
            ->get()
            ->sortBy(fn (DuesRecord $record) => [
                $record->member->group?->name,
                $record->member->no_urut ?? PHP_INT_MAX,
            ])
            ->values();

        return view('pdf.coupons', [
            'records' => $records,
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();
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

    /**
     * Kupon adalah tagihan, bukan arsip. Anggota yang sudah melunasi bulan itu
     * — misalnya membayar Januari–Desember sekaligus di kantor — tidak boleh
     * ikut tercetak lagi, karena kolektor akan menagihnya untuk kedua kalinya.
     * Setoran kolektor yang masih menunggu persetujuan juga tidak: uangnya sudah
     * berpindah tangan di lapangan. Bila setorannya ditolak, tagihannya kembali
     * BELUM BAYAR dan ikut tercetak lagi dengan sendirinya.
     */
    public function test_paid_and_pending_months_are_excluded_from_coupons(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);

        $belumBayar = Member::factory()->active()->create([
            'group_id' => $group->id,
            'name_indonesian' => 'Belum Bayar',
        ]);
        $lunas = Member::factory()->active()->create([
            'group_id' => $group->id,
            'name_indonesian' => 'Sudah Lunas',
        ]);
        $menunggu = Member::factory()->active()->create([
            'group_id' => $group->id,
            'name_indonesian' => 'Menunggu Persetujuan',
        ]);

        DuesRecord::factory()->forPeriod(2025, 12)->create(['member_id' => $belumBayar->id]);
        DuesRecord::factory()->forPeriod(2025, 12)->paid()->create(['member_id' => $lunas->id]);
        DuesRecord::factory()->forPeriod(2025, 12)->create([
            'member_id' => $menunggu->id,
            'status' => DuesStatus::MenungguPersetujuan,
        ]);

        $rendered = [];
        view()->composer('pdf.coupons', function ($view) use (&$rendered): void {
            $rendered[] = $view->getData();
        });

        $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
            'group' => $group->id,
        ]))->assertOk();

        $this->assertNotEmpty($rendered);

        $this->assertSame(
            ['Belum Bayar'],
            $rendered[0]['records']->map(fn (DuesRecord $record): string => $record->member->name_indonesian)->all(),
        );
    }

    /**
     * Konsekuensi langsung dari penyaringan di atas: satu kelompok yang seluruh
     * anggotanya sudah melunasi bulan itu kini menghasilkan NOL kupon — keadaan
     * yang dulu praktis mustahil. Lembarnya harus tetap terbit dengan
     * keterangan, bukan meledak karena tak ada satu pun kupon untuk ditata.
     */
    public function test_a_fully_paid_group_prints_a_page_saying_there_is_nothing_to_bill(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 1']);

        foreach (range(1, 3) as $ignored) {
            $member = Member::factory()->active()->create(['group_id' => $group->id]);
            DuesRecord::factory()->forPeriod(2025, 12)->paid()->create(['member_id' => $member->id]);
        }

        $rendered = [];
        view()->composer('pdf.coupons', function ($view) use (&$rendered): void {
            $rendered[] = $view->getData();
        });

        $response = $this->actingAs($admin)->get(route('dues.coupons', [
            'year' => 2025,
            'month' => 12,
            'group' => $group->id,
        ]));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertNotEmpty($rendered);
        $this->assertCount(0, $rendered[0]['records']);
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
