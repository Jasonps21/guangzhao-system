<?php

namespace Tests\Feature;

use App\Enums\PrintedDocument;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use App\Support\ActivityNarrator;
use App\Support\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Baris log mentah tidak terbaca manusia: eventnya "updated", subjeknya
 * "App\Models\User", propertinya array bersarang. Penerjemah inilah yang
 * mengubahnya jadi kalimat Indonesia — dan karena terpisah dari UI, bisa diuji
 * tanpa membuka satu halaman pun.
 */
class ActivityNarratorTest extends TestCase
{
    use RefreshDatabase;

    private ActivityNarrator $narrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->narrator = app(ActivityNarrator::class);
    }

    private function latest(): ActivityLog
    {
        return ActivityLog::query()->latest('id')->firstOrFail();
    }

    public function test_a_created_record_reads_as_indonesian(): void
    {
        MemberGroup::factory()->create(['name' => 'Kelompok 3']);

        $log = $this->latest();

        $this->assertSame('Kelompok ditambahkan', $this->narrator->title($log));
        $this->assertSame('Kelompok 3', $this->narrator->subject($log));
    }

    public function test_a_deleted_record_reads_as_indonesian(): void
    {
        MemberGroup::factory()->create(['name' => 'Kelompok 9'])->delete();

        $this->assertSame('Kelompok dihapus', $this->narrator->title($this->latest()));
    }

    /**
     * Inti kegunaannya: Super Admin harus bisa melihat nilai lama dan barunya
     * tanpa menerjemahkan sendiri kode enum seperti "super_admin".
     */
    public function test_an_update_lists_what_changed_from_and_to(): void
    {
        $user = User::factory()->collector()->create(['name' => 'Daya']);

        $user->update(['role' => UserRole::Admin, 'is_active' => false]);

        $changes = $this->narrator->changes($this->latest());

        $this->assertSame(
            ['Peran' => ['dari' => 'Kolektor', 'jadi' => 'Admin / Bendahara']],
            ['Peran' => $changes['Peran']],
        );
        $this->assertSame(['dari' => 'Ya', 'jadi' => 'Tidak'], $changes['Aktif']);
    }

    public function test_money_is_shown_as_rupiah(): void
    {
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $member->update(['monthly_dues_amount' => 25000]);

        $this->assertSame(
            ['dari' => 'Rp 20.000', 'jadi' => 'Rp 25.000'],
            $this->narrator->changes($this->latest())['Iuran per bulan'],
        );
    }

    public function test_a_printing_entry_keeps_its_own_sentence(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['member_number' => '1-007']);

        $this->actingAs($admin);
        app(ActivityRecorder::class)->printed(
            PrintedDocument::KartuAnggota,
            ['no_anggota' => '1-007'],
            $member,
        );

        $log = $this->latest();

        $this->assertSame('Cetak kartu anggota', $this->narrator->title($log));
        $this->assertSame($member->displayName(), $this->narrator->subject($log));
    }

    public function test_an_entry_whose_subject_is_gone_still_reads(): void
    {
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 7']);
        $group->forceDelete();

        $log = $this->latest();

        $this->assertSame('Kelompok dihapus', $this->narrator->title($log));
        $this->assertSame('Kelompok 7', $this->narrator->subject($log));
    }
}
