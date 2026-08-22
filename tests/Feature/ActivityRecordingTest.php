<?php

namespace Tests\Feature;

use App\Enums\ActivityCategory;
use App\Enums\RelationshipType;
use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\ActivityLog;
use App\Models\CondolencePayment;
use App\Models\DuesReceipt;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberRelationship;
use App\Models\MemberVisit;
use App\Models\OrganizationSetting;
use App\Models\SocialFundRecord;
use App\Models\User;
use App\Support\DuesGenerator;
use App\Support\OpeningBalanceImporter;
use App\Support\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Jejak audit hanya berguna kalau lengkap. Yang diuji di sini adalah apa yang
 * masuk ke dalam log — bukan tampilannya (lihat ActivityLogPanelTest).
 */
class ActivityRecordingTest extends TestCase
{
    use RefreshDatabase;

    /** Log yang tercatat sejak baris ini dipanggil, terbaru lebih dulu. */
    private function logged(?ActivityCategory $category = null): Collection
    {
        return ActivityLog::query()
            ->when($category, fn ($q) => $q->where('log_name', $category->value))
            ->latest('id')
            ->get();
    }

    // ── Masuk & keluar ──────────────────────────────────────────────────

    public function test_signing_in_is_recorded_against_the_user(): void
    {
        $user = User::factory()->create(['username' => 'bendahara']);

        Livewire::test(Login::class)
            ->set('username', 'bendahara')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $entry = $this->logged(ActivityCategory::Autentikasi)->first();

        $this->assertNotNull($entry, 'Login berhasil tidak tercatat.');
        $this->assertSame('Masuk ke sistem', $entry->description);
        $this->assertTrue($entry->causer->is($user));
    }

    public function test_signing_out_is_recorded(): void
    {
        $user = User::factory()->collector()->create();

        $this->actingAs($user);
        Auth::logout();

        $entry = $this->logged(ActivityCategory::Autentikasi)->first();

        $this->assertNotNull($entry, 'Logout tidak tercatat.');
        $this->assertSame('Keluar dari sistem', $entry->description);
        $this->assertTrue($entry->causer->is($user));
    }

    /**
     * Kata sandi yang salah diketik tidak boleh ikut tersimpan: log audit
     * dibaca Super Admin dan ikut terbawa ke setiap cadangan basis data.
     */
    public function test_a_failed_sign_in_is_recorded_without_the_password(): void
    {
        User::factory()->create(['username' => 'bendahara']);

        Livewire::test(Login::class)
            ->set('username', 'bendahara')
            ->set('password', 'kata-sandi-yang-salah')
            ->call('login')
            ->assertHasErrors('username');

        $entry = $this->logged(ActivityCategory::Autentikasi)->first();

        $this->assertNotNull($entry, 'Login gagal tidak tercatat.');
        $this->assertSame('Gagal masuk ke sistem', $entry->description);
        $this->assertSame('bendahara', $entry->properties['nama_pengguna'] ?? null);
        $this->assertNull($entry->causer);
        $this->assertStringNotContainsString('kata-sandi-yang-salah', json_encode($entry->properties));
    }

    // ── Perubahan data yang sebelumnya tidak tercatat ───────────────────

    public function test_changing_a_user_is_recorded_without_any_password(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Budi']);

        $admin->update(['role' => UserRole::SuperAdmin, 'password' => 'rahasia-baru']);

        $entry = ActivityLog::query()->where('subject_type', User::class)->latest('id')->first();

        $this->assertNotNull($entry, 'Perubahan pengguna tidak tercatat.');
        $this->assertSame('super_admin', $entry->properties['attributes']['role'] ?? null);

        $dumped = json_encode($entry->properties);
        $this->assertStringNotContainsString('rahasia-baru', $dumped);
        $this->assertStringNotContainsString('password', $dumped);
    }

    public function test_creating_a_group_is_recorded(): void
    {
        MemberGroup::factory()->create(['name' => 'Kelompok 9']);

        $entry = ActivityLog::query()->where('subject_type', MemberGroup::class)->latest('id')->first();

        $this->assertNotNull($entry, 'Kelompok baru tidak tercatat.');
        $this->assertSame('Kelompok 9', $entry->properties['attributes']['name'] ?? null);
    }

    public function test_a_house_visit_is_recorded(): void
    {
        MemberVisit::factory()->create();

        $this->assertNotNull(
            ActivityLog::query()->where('subject_type', MemberVisit::class)->first(),
            'Kunjungan rumah tidak tercatat.',
        );
    }

    public function test_changing_organization_settings_is_recorded(): void
    {
        OrganizationSetting::current()->update(['chairman_name' => 'Bapak Ho']);

        $entry = ActivityLog::query()->where('subject_type', OrganizationSetting::class)->latest('id')->first();

        $this->assertNotNull($entry, 'Perubahan konfigurasi organisasi tidak tercatat.');
        $this->assertSame('Bapak Ho', $entry->properties['attributes']['chairman_name'] ?? null);
    }

    /**
     * Tiap hubungan keluarga otomatis membuat baris kebalikannya. Yang dicatat
     * hanya yang dilakukan pengguna, supaya satu tindakan tidak tampak dua kali.
     */
    public function test_a_family_relationship_records_one_entry_not_two(): void
    {
        $andi = Member::factory()->create();
        $junita = Member::factory()->create();

        MemberRelationship::query()->create([
            'member_id' => $andi->id,
            'related_member_id' => $junita->id,
            'relationship_type' => RelationshipType::Spouse,
        ]);

        $this->assertCount(
            1,
            ActivityLog::query()->where('subject_type', MemberRelationship::class)->get(),
        );
    }

    // ── Aksi massal diringkas ───────────────────────────────────────────

    public function test_generating_dues_records_one_summary_not_one_row_per_member(): void
    {
        Member::factory()->active()->count(5)->create(['monthly_dues_amount' => 20000]);

        app(DuesGenerator::class)->generate(2026, 9);

        $this->assertCount(
            0,
            ActivityLog::query()->where('subject_type', DuesRecord::class)->get(),
            'Tagihan massal masih dicatat satu per satu.',
        );

        $entry = $this->logged()->first();

        $this->assertSame('Generate tagihan iuran', $entry->description);
        $this->assertSame(5, $entry->properties['jumlah_tagihan'] ?? null);
        $this->assertSame('September 2026', $entry->properties['periode'] ?? null);
    }

    // ── Pencetakan ──────────────────────────────────────────────────────

    public function test_printing_coupons_is_recorded_with_period_and_sheet_count(): void
    {
        $admin = User::factory()->admin()->create();
        $group = MemberGroup::factory()->create(['name' => 'Kelompok 3']);
        $member = Member::factory()->active()->create(['group_id' => $group->id]);
        DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2026,
            'period_month' => 9,
        ]);

        $this->actingAs($admin)
            ->get(route('dues.coupons', ['year' => 2026, 'month' => 9, 'group' => $group->id]))
            ->assertOk();

        $entry = $this->logged(ActivityCategory::Cetak)->first();

        $this->assertNotNull($entry, 'Pencetakan kupon tidak tercatat.');
        $this->assertSame('Cetak kupon iuran', $entry->description);
        $this->assertTrue($entry->causer->is($admin));
        $this->assertSame('September 2026', $entry->properties['periode'] ?? null);
        $this->assertSame('Kelompok 3', $entry->properties['kelompok'] ?? null);
        $this->assertSame(1, $entry->properties['jumlah_kupon'] ?? null);
    }

    public function test_printing_a_member_card_names_the_member(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['member_number' => '1-042']);

        $this->actingAs($admin)->get(route('members.card', $member))->assertOk();

        $entry = $this->logged(ActivityCategory::Cetak)->first();

        $this->assertSame('Cetak kartu anggota', $entry->description);
        $this->assertTrue($entry->subject->is($member));
        $this->assertSame('1-042', $entry->properties['no_anggota'] ?? null);
    }

    /**
     * Kedelapan jenis cetakan harus tercatat, masing-masing tepat satu baris —
     * ini yang menjawab "harus bisa ditahu, cetak apa".
     */
    public function test_every_printable_document_is_recorded_exactly_once(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create();
        $record = DuesRecord::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2026,
            'period_month' => 9,
        ]);
        $receipt = DuesReceipt::factory()->create(['member_id' => $member->id]);
        $condolence = CondolencePayment::factory()->create(['member_id' => $member->id]);
        $socialFund = SocialFundRecord::factory()->create();

        $routes = [
            'Cetak kupon iuran' => route('dues.coupons', ['year' => 2026, 'month' => 9]),
            'Cetak kupon anggota terpilih' => route('dues.coupons.selected', ['year' => 2026, 'month' => 9, 'members' => [$member->id]]),
            'Cetak daftar anggota' => route('members.list'),
            'Cetak kartu anggota' => route('members.card', $member),
            'Cetak tanda terima uang duka' => route('condolence.receipt', $condolence),
            'Cetak tanda pemberian uang duka' => route('condolence.handover', $condolence),
            'Cetak nota dana sosial' => route('social-fund.receipt', $socialFund),
            'Cetak nota pembayaran iuran' => route('dues.receipt', $receipt),
        ];

        foreach ($routes as $expected => $url) {
            ActivityLog::query()->where('log_name', ActivityCategory::Cetak->value)->delete();

            $this->actingAs($admin)->get($url)->assertOk();

            $entries = $this->logged(ActivityCategory::Cetak);

            $this->assertCount(1, $entries, "'{$expected}' tidak tercatat tepat satu baris.");
            $this->assertSame($expected, $entries->first()->description);
            $this->assertTrue($entries->first()->causer->is($admin));
        }

        unset($record);
    }

    /**
     * Satu setoran melunasi 6-12 bulan sekaligus. Yang dicari Super Admin
     * adalah "siapa menyetujui setoran siapa", bukan 7 baris terpisah.
     */
    public function test_approving_a_submission_records_one_summary(): void
    {
        $collector = User::factory()->collector()->create(['name' => 'Daya']);
        $admin = User::factory()->admin()->create(['name' => 'Bendahara']);
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);

        ActivityLog::query()->delete();

        app(PaymentService::class)->approveSubmission($submission->refresh(), $admin);

        $entries = $this->logged();

        $this->assertCount(1, $entries, 'Persetujuan setoran tidak diringkas jadi satu baris.');
        $this->assertSame('Setoran kolektor disetujui', $entries->first()->description);
        $this->assertSame('Daya', $entries->first()->properties['kolektor'] ?? null);
        $this->assertSame(7, $entries->first()->properties['jumlah_bulan'] ?? null);
        $this->assertSame('Bendahara', $entries->first()->properties['diputuskan_oleh'] ?? null);
    }

    public function test_rejecting_a_submission_records_one_summary(): void
    {
        $collector = User::factory()->collector()->create();
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 20000]);

        $submission = app(PaymentService::class)->submitRange($member, 2026, 6, 12, $collector);

        ActivityLog::query()->delete();

        app(PaymentService::class)->rejectSubmission($submission->refresh(), $admin, 'Uang belum diterima');

        $entries = $this->logged();

        $this->assertCount(1, $entries, 'Penolakan setoran tidak diringkas jadi satu baris.');
        $this->assertSame('Setoran kolektor ditolak', $entries->first()->description);
    }

    public function test_importing_opening_balances_records_one_summary(): void
    {
        Member::factory()->active()->create([
            'member_number' => 'A-014',
            'monthly_dues_amount' => 30000,
        ]);

        app(OpeningBalanceImporter::class)->import(
            [['row' => 1, 'member_number' => 'A-014', 'last_paid_month' => 1]],
            2026,
            throughMonth: 7,
        );

        $entries = $this->logged(ActivityCategory::Berkas);

        $this->assertCount(1, $entries, 'Impor saldo awal tidak tercatat tepat satu baris.');
        $this->assertSame('Impor saldo awal iuran', $entries->first()->description);
        $this->assertSame(1, $entries->first()->properties['anggota_diproses'] ?? null);

        $this->assertCount(
            0,
            ActivityLog::query()->where('subject_type', DuesRecord::class)->get(),
            'Impor massal masih dicatat satu per satu.',
        );
    }

    /**
     * Uji coba tidak menulis apa pun, jadi tidak boleh meninggalkan jejak yang
     * membuat seolah data sudah berubah.
     */
    public function test_a_dry_run_import_is_not_recorded(): void
    {
        Member::factory()->active()->create([
            'member_number' => 'A-014',
            'monthly_dues_amount' => 30000,
        ]);

        app(OpeningBalanceImporter::class)->import(
            [['row' => 1, 'member_number' => 'A-014', 'last_paid_month' => 1]],
            2026,
            throughMonth: 7,
            dryRun: true,
        );

        $this->assertCount(0, $this->logged(ActivityCategory::Berkas));
    }

    public function test_exporting_the_monthly_recap_is_recorded(): void
    {
        Excel::fake();

        $this->artisan('dues:export-monthly', ['year' => 2026, 'month' => 7])->assertSuccessful();

        $entry = $this->logged(ActivityCategory::Berkas)->first();

        $this->assertNotNull($entry, 'Ekspor rekap bulanan tidak tercatat.');
        $this->assertSame('Ekspor rekap iuran bulanan', $entry->description);
        $this->assertSame('Juli 2026', $entry->properties['periode'] ?? null);
    }
}
