<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nomor anggota adalah turunan dari kelompok dan no. urut ("7-057"), bukan
 * isian terpisah. Dulu keduanya disimpan sendiri-sendiri, sehingga setelah
 * no. urut dibetulkan, aplikasi lapangan menampilkan "57" di samping kode
 * "7-056-2" — dua orang tampak sama-sama bernomor 56.
 */
class MemberNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_number_is_derived_from_group_and_no_urut(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);

        $member = Member::factory()->create([
            'group_id' => $group->id,
            'no_urut' => 57,
            'member_number' => '7-056-2',
        ]);

        $this->assertSame('7-057', $member->refresh()->member_number);
    }

    public function test_changing_no_urut_renumbers_the_member(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 9']);
        $member = Member::factory()->create(['group_id' => $group->id, 'no_urut' => 32]);

        $member->update(['no_urut' => 33]);

        $this->assertSame('9-033', $member->refresh()->member_number);
    }

    public function test_moving_to_another_group_changes_the_prefix(): void
    {
        $from = MemberGroup::factory()->create(['name' => 'Kelompok 3']);
        $to = MemberGroup::factory()->create(['name' => 'Kelompok 10 (Bebas Iuran)']);
        $member = Member::factory()->create(['group_id' => $from->id, 'no_urut' => 4]);

        $member->update(['group_id' => $to->id]);

        $this->assertSame('10-004', $member->refresh()->member_number);
    }

    public function test_a_group_name_without_a_number_falls_back_to_its_id(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Wilayah Timur']);

        $member = Member::factory()->create(['group_id' => $group->id, 'no_urut' => 1]);

        $this->assertSame($group->id.'-001', $member->member_number);
    }

    public function test_member_number_is_left_alone_without_a_no_urut(): void
    {
        $member = Member::factory()->create(['no_urut' => null, 'member_number' => 'GZ-12345']);

        $this->assertSame('GZ-12345', $member->member_number);
    }

    public function test_create_form_saves_the_derived_member_number(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Johnny Sunjaya',
                'group_id' => $group->id,
                'no_urut' => 57,
                'monthly_dues_amount' => 25000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', [
            'name_indonesian' => 'Johnny Sunjaya',
            'member_number' => '7-057',
        ]);
    }

    public function test_choosing_a_group_suggests_the_next_free_no_urut(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        Member::factory()->create(['group_id' => $group->id, 'no_urut' => 140]);

        Livewire::test(CreateMember::class)
            ->fillForm(['group_id' => $group->id])
            ->assertSchemaStateSet([
                'no_urut' => 141,
                'member_number' => '7-141',
            ]);
    }

    public function test_group_and_no_urut_are_required(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Tanpa Kelompok',
                'group_id' => null,
                'no_urut' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['group_id' => 'required', 'no_urut' => 'required']);
    }

    public function test_no_urut_cannot_be_used_twice_in_one_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        Member::factory()->create(['group_id' => $group->id, 'no_urut' => 56]);

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Orang Lain',
                'group_id' => $group->id,
                'no_urut' => 56,
            ])
            ->call('create')
            ->assertHasFormErrors(['no_urut']);

        $this->assertSame(1, Member::query()->where('group_id', $group->id)->count());
    }

    public function test_the_same_no_urut_is_fine_in_a_different_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $seven = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $nine = MemberGroup::factory()->create(['name' => 'Kelompok 9']);
        Member::factory()->create(['group_id' => $seven->id, 'no_urut' => 56]);

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Anggota K9',
                'group_id' => $nine->id,
                'no_urut' => 56,
                'monthly_dues_amount' => 25000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', ['name_indonesian' => 'Anggota K9', 'member_number' => '9-056']);
    }

    /**
     * Pengurus lama memperlakukan no. urut sebagai kursi: begitu pemiliknya
     * keluar atau meninggal, kursinya diisi orang baru. Kode orang yang keluar
     * diarsipkan agar nomornya bebas dipakai lagi.
     */
    public function test_leaving_the_association_archives_the_code(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $member = Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 14]);

        $member->update(['status' => MemberStatus::MengundurkanDiri]);

        $this->assertSame('7-014-K', $member->refresh()->member_number);
        $this->assertSame(14, $member->no_urut);
    }

    public function test_a_seat_vacated_twice_keeps_both_archived_codes_unique(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $first = Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 14]);
        $first->update(['status' => MemberStatus::Meninggal]);

        $second = Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 14]);
        $second->update(['status' => MemberStatus::MengundurkanDiri]);

        $this->assertSame('7-014-K', $first->refresh()->member_number);
        $this->assertSame('7-014-K2', $second->refresh()->member_number);
    }

    public function test_resaving_an_archived_member_keeps_its_archived_code(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $member = Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 14]);
        $member->update(['status' => MemberStatus::Meninggal]);

        $member->update(['notes' => 'Catatan baru']);

        $this->assertSame('7-014-K', $member->refresh()->member_number);
    }

    public function test_a_new_member_can_take_the_seat_of_one_who_left(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $leaver = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 14,
            'name_indonesian' => 'Jony Tandiamal',
        ]);
        $leaver->update(['status' => MemberStatus::MengundurkanDiri]);

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Penghuni Baru',
                'group_id' => $group->id,
                'no_urut' => 14,
                'monthly_dues_amount' => 20000,
            ])
            ->assertSee('Jony Tandiamal')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', ['name_indonesian' => 'Penghuni Baru', 'member_number' => '7-014']);
        $this->assertSame('7-014-K', $leaver->refresh()->member_number);
    }

    public function test_an_inactive_member_does_not_block_the_unique_check_but_an_active_one_does(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 20]);

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name_indonesian' => 'Anggota Tidak Aktif',
                'group_id' => $group->id,
                'no_urut' => 20,
                'status' => MemberStatus::Meninggal->value,
                'monthly_dues_amount' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('members', ['name_indonesian' => 'Anggota Tidak Aktif', 'member_number' => '7-020-K']);
    }

    public function test_reactivating_into_an_occupied_seat_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $leaver = Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 14, 'phone' => null]);
        $leaver->update(['status' => MemberStatus::MengundurkanDiri]);
        Member::factory()->active()->create(['group_id' => $group->id, 'no_urut' => 14]);

        Livewire::test(EditMember::class, ['record' => $leaver->id])
            ->fillForm(['status' => MemberStatus::Aktif->value])
            ->call('save')
            ->assertHasFormErrors(['no_urut']);

        $this->assertSame('7-014-K', $leaver->refresh()->member_number);
    }

    public function test_editing_keeps_its_own_no_urut_without_tripping_the_unique_rule(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $member = Member::factory()->active()->create([
            'group_id' => $group->id,
            'no_urut' => 56,
            'phone' => null,
        ]);

        Livewire::test(EditMember::class, ['record' => $member->id])
            ->fillForm(['no_urut' => 58])
            ->assertSchemaStateSet(['member_number' => '7-058'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('7-058', $member->refresh()->member_number);
    }
}
