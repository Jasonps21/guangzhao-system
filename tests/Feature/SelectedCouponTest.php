<?php

namespace Tests\Feature;

use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectedCouponTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tanpa mode datar, tiga anggota dari tiga kelompok akan menghasilkan tiga
     * lembar yang masing-masing hanya berisi satu kupon asli — persis pemborosan
     * kertas yang membuat fitur kupon cadangan dibuat.
     */
    public function test_members_from_different_groups_share_one_sheet(): void
    {
        $this->makeMemberWithRecord('Kelompok 1', 1);
        $this->makeMemberWithRecord('Kelompok 5', 2);
        $this->makeMemberWithRecord('Kelompok 8', 3);

        $html = $this->renderFlat();

        $this->assertStringNotContainsString(
            'page-break-after',
            $html,
            'Tiga kupon harus muat di satu lembar, bukan tiga.',
        );
        $this->assertSame(10, substr_count($html, 'class="inner"'));
        $this->assertSame(7, substr_count($html, 'class="fill fill-number"'));
    }

    /**
     * Lembar campur kelompok tidak punya satu kelompok pun yang benar untuk
     * kupon cadangan, jadi barisnya dikosongkan. Kupon aslinya tetap wajib
     * mencetak kelompoknya sendiri.
     */
    public function test_spares_blank_the_group_while_real_coupons_keep_theirs(): void
    {
        $this->makeMemberWithRecord('Kelompok 1', 1);
        $this->makeMemberWithRecord('Kelompok 5', 2);
        $this->makeMemberWithRecord('Kelompok 8', 3);

        $html = $this->renderFlat();

        $this->assertSame(7, substr_count($html, 'class="fill fill-group"'));
        $this->assertSame(1, substr_count($html, '<td>Kelompok 1</td>'));
        $this->assertSame(1, substr_count($html, '<td>Kelompok 5</td>'));
        $this->assertSame(1, substr_count($html, '<td>Kelompok 8</td>'));
    }

    /**
     * Cetak per kelompok tidak boleh ikut berubah hanya karena mode baru ada.
     */
    public function test_the_group_split_stays_on_by_default(): void
    {
        $this->makeMemberWithRecord('Kelompok 1', 1);
        $this->makeMemberWithRecord('Kelompok 5', 2);

        $html = view('pdf.coupons', [
            'records' => $this->records(),
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();

        $this->assertStringContainsString('page-break-after', $html);
        $this->assertSame(20, substr_count($html, 'class="inner"'));
    }

    /**
     * Anggota baru yang tagihannya belum digenerate tetap harus bisa dapat
     * kupon; nominalnya diambil dari iuran bulanan di master data.
     */
    public function test_a_member_without_a_dues_record_still_gets_a_coupon(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $data = $this->captureCouponViewData($admin, [$member->id]);

        $this->assertCount(1, $data['records']);
        $this->assertSame(25000.0, (float) $data['records']->first()->amount_due);
        $this->assertFalse(
            $data['records']->first()->exists,
            'Tagihan penggantinya tidak boleh tersimpan ke database.',
        );
    }

    /**
     * Kupon pengganti harus identik dengan yang rusak, jadi nominalnya diambil
     * dari tagihan yang ada — bukan dari master data yang bisa sudah berubah.
     */
    public function test_an_existing_dues_record_wins_over_the_master_amount(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
            'amount_due' => 15000,
        ]);

        $data = $this->captureCouponViewData($admin, [$member->id]);

        $this->assertSame(15000.0, (float) $data['records']->first()->amount_due);
    }

    /**
     * Mencetak kupon bukan menagih — tidak boleh ada baris tagihan yang lahir.
     */
    public function test_printing_creates_no_dues_records(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        $before = DuesRecord::count();

        $this->actingAs($admin)->get(route('dues.coupons.selected', [
            'year' => 2025,
            'month' => 12,
            'members' => [$member->id],
        ]))->assertOk();

        $this->assertSame($before, DuesRecord::count());
    }

    /**
     * Menyaring di pencarian saja tidak cukup: id anggota non-aktif yang
     * diketik langsung di URL juga harus ditolak.
     */
    public function test_inactive_members_are_dropped_even_when_their_id_is_passed(): void
    {
        $admin = User::factory()->admin()->create();
        $active = Member::factory()->active()->create(['name_indonesian' => 'Masih Aktif']);
        $inactive = Member::factory()->inactive()->create(['name_indonesian' => 'Sudah Pindah']);

        $data = $this->captureCouponViewData($admin, [$active->id, $inactive->id]);

        $this->assertCount(1, $data['records']);
        $this->assertSame($active->id, $data['records']->first()->member->id);
    }

    public function test_the_route_streams_a_flat_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();

        $response = $this->actingAs($admin)->get(route('dues.coupons.selected', [
            'year' => 2025,
            'month' => 12,
            'members' => [$member->id],
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /**
     * Data yang diterima template kupon saat mencetak anggota terpilih.
     *
     * @param  list<int>  $memberIds
     * @return array<string, mixed>
     */
    private function captureCouponViewData(User $admin, array $memberIds): array
    {
        $captured = [];
        view()->composer('pdf.coupons', function ($view) use (&$captured): void {
            $captured[] = $view->getData();
        });

        $this->actingAs($admin)->get(route('dues.coupons.selected', [
            'year' => 2025,
            'month' => 12,
            'members' => $memberIds,
        ]))->assertOk();

        $this->assertNotEmpty($captured, 'Template kupon tidak pernah dirender.');
        $this->assertFalse($captured[0]['splitPerGroup'], 'Cetak anggota terpilih harus memakai mode datar.');

        return $captured[0];
    }

    private function makeMemberWithRecord(string $groupName, int $noUrut): Member
    {
        $group = MemberGroup::factory()->create(['name' => $groupName]);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => $noUrut,
        ]);

        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2025,
            'period_month' => 12,
        ]);

        return $member;
    }

    /**
     * @return Collection<int, DuesRecord>
     */
    private function records(): Collection
    {
        return DuesRecord::query()
            ->with(['member.group'])
            ->forPeriod(2025, 12)
            ->get()
            ->sortBy(fn (DuesRecord $record) => [
                $record->member->group?->name,
                $record->member->no_urut ?? PHP_INT_MAX,
            ])
            ->values();
    }

    private function renderFlat(): string
    {
        return view('pdf.coupons', [
            'records' => $this->records(),
            'period' => 'Desember 2025',
            'organization' => OrganizationSetting::current(),
            'logo' => null,
            'splitPerGroup' => false,
        ])->render();
    }
}
