<?php

namespace Tests\Feature;

use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\OrganizationSetting;
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
