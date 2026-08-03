<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Enums\VisitOutcome;
use App\Enums\WhatsappOwner;
use App\Livewire\Kolektor\Billing;
use App\Models\DuesRecord;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MemberVisitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: MemberGroup}
     */
    private function collectorWithGroup(): array
    {
        $collector = User::factory()->collector()->create();
        $group = MemberGroup::factory()->create();
        $collector->groups()->attach($group);

        return [$collector, $group];
    }

    public function test_payment_is_blocked_until_house_location_is_complete(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id)
            ->assertSet('activeMemberId', $member->id);

        // Belum lunas — kolektor diarahkan melengkapi lokasi dulu.
        $this->assertSame(DuesStatus::BelumBayar, $record->refresh()->status);
    }

    /**
     * Kolektor menekan simpan dengan panel masih kosong. Semua yang kurang
     * harus muncul sekaligus — sebelumnya galat titik GPS melempar lebih dulu
     * sehingga nomor telepon tidak pernah sempat diperiksa, dan kolektor
     * mengira nomor memang tidak wajib.
     */
    public function test_pressing_save_on_an_empty_panel_reports_every_missing_field_at_once(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->call('saveVisit')
            ->assertHasErrors(['lat', 'lng', 'photo', 'phone']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    /**
     * Kolektor membaca pesan ini sambil berdiri di depan pintu — tidak boleh
     * ada kalimat Inggris bawaan Laravel.
     */
    public function test_validation_messages_are_written_in_indonesian(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        $component = Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->call('saveVisit');

        $errors = $component->errors()->all();

        $this->assertNotEmpty($errors);
        foreach ($errors as $message) {
            $this->assertStringNotContainsStringIgnoringCase('field is required', $message);
            $this->assertStringNotContainsStringIgnoringCase('must be', $message);
            $this->assertStringNotContainsStringIgnoringCase('The selected', $message);
        }
    }

    public function test_first_visit_requires_gps_and_photo(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->call('saveVisit')
            ->assertHasErrors(['lat', 'lng', 'photo']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    public function test_collector_records_a_visit_with_gps_and_photo(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['address' => 'Alamat lama', 'phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', '081234567890')
            ->set('houseAddress', 'Jl. Baru No. 5')
            ->set('note', 'pagar hijau, sebelah warung')
            ->call('saveVisit')
            ->assertHasNoErrors()
            ->assertSet('activeMemberId', null);

        $member->refresh();
        $this->assertTrue($member->hasCompleteLocation());
        $this->assertEqualsWithDelta(-5.1476, (float) $member->latitude, 0.0001);
        $this->assertSame('Jl. Baru No. 5', $member->address);
        $this->assertSame('081234567890', $member->phone);
        $this->assertSame($collector->id, $member->location_updated_by);
        $this->assertNotNull($member->location_updated_at);
        Storage::disk('public')->assertExists($member->house_photo_path);

        $visit = MemberVisit::query()->firstOrFail();
        $this->assertSame($member->id, $visit->member_id);
        $this->assertSame($collector->id, $visit->user_id);
        $this->assertNotNull($visit->photo_path);
    }

    public function test_confirm_location_records_a_confirmation_visit(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('confirmLocation', $member->id);

        $this->assertSame(1, MemberVisit::query()->where('member_id', $member->id)->count());
        $this->assertSame($collector->id, $member->refresh()->location_updated_by);
        $this->assertSame(VisitOutcome::Bertemu, MemberVisit::query()->firstOrFail()->outcome);
    }

    public function test_already_located_member_can_pay_without_recapturing(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        $record = DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('markPaid', $record->id);

        $this->assertSame(DuesStatus::Lunas, $record->refresh()->status);
    }

    public function test_member_exposes_google_maps_links_when_located(): void
    {
        $member = Member::factory()->withLocation()->create();

        $this->assertStringContainsString('-5.1476', (string) $member->googleMapsUrl());
        $this->assertStringContainsString('destination=', (string) $member->googleMapsDirectionsUrl());
        $this->assertNull(Member::factory()->create(['latitude' => null, 'longitude' => null])->googleMapsUrl());
    }

    /**
     * Jalan keluar saat foto gagal terkirim di lapangan: titik GPS tetap
     * tersimpan supaya perjalanan kolektor tidak terbuang.
     */
    public function test_collector_can_save_the_gps_point_when_the_photo_fails_to_upload(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('phone', '081234567890')
            ->set('houseAddress', 'Jl. Anuang 25C')
            ->call('saveLocationOnly')
            ->assertHasNoErrors()
            ->assertSet('activeMemberId', null);

        $member->refresh();
        $this->assertEqualsWithDelta(-5.1476, (float) $member->latitude, 0.0001);
        $this->assertSame('Jl. Anuang 25C', $member->address);
        $this->assertSame(1, MemberVisit::query()->count());

        // Kebijakan "wajib sekali" tidak boleh ikut longgar: tanpa foto,
        // lokasinya tetap dianggap belum lengkap sehingga belum bisa lunas.
        $this->assertNull($member->house_photo_path);
        $this->assertFalse($member->hasCompleteLocation());
    }

    public function test_saving_the_gps_point_alone_still_requires_a_gps_point(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create();

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->call('saveLocationOnly')
            ->assertHasErrors(['lat', 'lng']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    /**
     * Anggota lansia sering hanya punya telepon rumah, sementara yang bisa
     * dihubungi lewat WhatsApp adalah anak atau menantunya. Keduanya informasi
     * berbeda dan harus punya kolomnya masing-masing.
     */
    public function test_it_records_a_relatives_whatsapp_number_beside_the_members_own_line(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', '0411-362589')
            ->set('whatsappPhone', '0852 9281 9058')
            ->set('whatsappOwner', WhatsappOwner::AnakMenantu->value)
            ->call('saveVisit')
            ->assertHasNoErrors();

        $member->refresh();
        $this->assertSame('0411362589', $member->phone);
        $this->assertSame('085292819058', $member->whatsapp_phone);
        $this->assertSame(WhatsappOwner::AnakMenantu, $member->whatsapp_owner);
    }

    public function test_a_whatsapp_number_alone_is_enough_to_save_the_visit(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', null)
            ->set('whatsappPhone', '081234567890')
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertSame('081234567890', $member->refresh()->whatsapp_phone);
    }

    public function test_a_whatsapp_number_defaults_to_belonging_to_the_member(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('whatsappPhone', '081234567890')
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertSame(WhatsappOwner::Sendiri, $member->refresh()->whatsapp_owner);
    }

    public function test_it_rejects_a_malformed_whatsapp_number(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('whatsappPhone', '0852 / 0813 5497 6704')
            ->call('saveVisit')
            ->assertHasErrors(['whatsappPhone']);
    }

    public function test_the_card_flags_a_member_reachable_by_line_but_not_by_whatsapp(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create([
            'phone' => '0411362589',
            'whatsapp_phone' => null,
        ]);
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSee('Nomor WA belum ada');
    }

    public function test_a_visit_cannot_be_saved_without_any_number_at_all(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->call('saveVisit')
            ->assertHasErrors(['phone']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    public function test_saving_the_gps_point_alone_also_requires_a_number(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->call('saveLocationOnly')
            ->assertHasErrors(['phone']);

        $this->assertSame(0, MemberVisit::query()->count());
    }

    public function test_it_rejects_two_numbers_crammed_into_one_field(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', '3613717 / 085343591718')
            ->call('saveVisit')
            ->assertHasErrors(['phone']);

        $this->assertNull($member->refresh()->phone);
    }

    /**
     * Anggota kebanyakan lansia yang hanya memakai telepon rumah — nomor
     * seperti itu tidak boleh ditolak, kalau tidak kunjungannya buntu.
     */
    public function test_it_accepts_a_landline_because_many_members_have_no_mobile(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', '0411-362589')
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertSame('0411362589', $member->refresh()->phone);
    }

    public function test_it_stores_the_phone_number_in_a_tidy_format(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => null]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', '+62 895 8034 5498')
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertSame('089580345498', $member->refresh()->phone);
    }

    public function test_it_prefills_the_number_a_member_already_has(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => '082291297777']);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->assertSet('phone', '082291297777');
    }

    /**
     * Sembilan anggota menyimpan dua nomor sekaligus di kolom ini. Bila nilai
     * itu diisikan apa adanya, kolektor terjebak: nomor yang sudah ada di sana
     * ditolak sistem, dan kunjungannya tidak bisa disimpan.
     */
    public function test_it_leaves_the_field_blank_when_the_stored_value_holds_two_numbers(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create([
            'phone' => '3613717 / 085343591718',
        ]);
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->assertSet('phone', null)
            ->assertSee('3613717 / 085343591718');
    }

    public function test_it_prefills_a_landline_a_member_already_has(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => '0411-362589']);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->assertSet('phone', '0411362589');
    }

    public function test_the_card_flags_a_member_with_no_phone_number(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $without = Member::factory()->active()->inGroup($group)->create(['phone' => null]);
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $without->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSee('Nomor telepon belum ada');
    }

    public function test_the_card_does_not_flag_a_member_who_already_has_a_number(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $member = Member::factory()->active()->inGroup($group)->create(['phone' => '082291297777']);
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->create([
            'member_id' => $member->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertDontSee('Nomor telepon belum ada');
    }

    /**
     * Iuran yang sudah dilunasi di kantor menutup seluruh zona aksi kartu,
     * sehingga tidak ada satu pun tombol untuk membuka panel kunjungan —
     * lokasi rumah anggota itu jadi mustahil dilengkapi kolektor.
     */
    public function test_location_entry_stays_reachable_after_the_office_marked_the_dues_paid(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $admin = User::factory()->create();
        $member = Member::factory()->active()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->paid()->create([
            'member_id' => $member->id,
            'collection_method' => CollectionMethod::Admin,
            'recorded_by' => $admin->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSeeHtml('wire:click="openVisit('.$member->id.')"');
    }

    public function test_collector_can_record_the_visit_of_a_member_already_marked_paid(): void
    {
        Storage::fake('public');
        [$collector, $group] = $this->collectorWithGroup();
        $admin = User::factory()->create();
        $member = Member::factory()->active()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->paid()->create([
            'member_id' => $member->id,
            'collection_method' => CollectionMethod::Admin,
            'recorded_by' => $admin->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->call('openVisit', $member->id)
            ->assertSee('Kunjungan Rumah')
            ->set('lat', -5.1476)
            ->set('lng', 119.4327)
            ->set('photo', UploadedFile::fake()->image('rumah.jpg', 800, 600))
            ->set('phone', '081234567890')
            ->call('saveVisit')
            ->assertHasNoErrors();

        $this->assertTrue($member->refresh()->hasCompleteLocation());
    }

    public function test_a_paid_member_with_complete_location_keeps_the_change_location_action(): void
    {
        [$collector, $group] = $this->collectorWithGroup();
        $admin = User::factory()->create();
        $member = Member::factory()->active()->withLocation()->inGroup($group)->create();
        DuesRecord::factory()->forPeriod((int) now()->year, (int) now()->month)->paid()->create([
            'member_id' => $member->id,
            'collection_method' => CollectionMethod::Admin,
            'recorded_by' => $admin->id,
        ]);

        Livewire::actingAs($collector)
            ->test(Billing::class, ['group' => $group])
            ->assertSeeHtml('wire:click="openVisit('.$member->id.')"')
            ->assertSeeHtml('wire:click="confirmLocation('.$member->id.')"');
    }
}
