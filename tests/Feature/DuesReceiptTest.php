<?php

namespace Tests\Feature;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use App\Filament\Pages\PembayaranKantor;
use App\Models\DuesReceipt;
use App\Models\Member;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Support\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DuesReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_at_the_office_issues_a_printable_receipt(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->mountAction('bayarIuran')
            ->fillForm([
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 1,
                'to_month' => 6,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $receipt = DuesReceipt::sole();

        $this->assertSame($member->id, $receipt->member_id);
        $this->assertSame(2026, $receipt->period_year);
        $this->assertSame(1, $receipt->from_month);
        $this->assertSame(6, $receipt->to_month);
        $this->assertSame(6, $receipt->months_count);
        $this->assertSame('150000.00', $receipt->total_amount);
        $this->assertSame($admin->id, $receipt->issued_by);
    }

    public function test_total_is_prefilled_from_the_outstanding_dues(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->mountAction('bayarIuran')
            ->fillForm([
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 1,
                'to_month' => 4,
            ])
            // 4 bulan × Rp 25.000, dan nama petugas terisi sebagai penerima.
            ->assertSchemaStateSet([
                'total_amount' => 100000.0,
                'received_by_name' => $admin->name,
            ]);
    }

    public function test_the_typed_total_wins_over_the_calculated_dues(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->callAction('bayarIuran', data: [
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 1,
                'to_month' => 6,
                // Anggota membayar lebih dari tagihannya; nota harus mengikuti
                // uang yang benar-benar diterima, bukan hitungan iuran.
                'total_amount' => 200000,
                'received_by_name' => 'Bendahara Lim',
            ])
            ->assertHasNoActionErrors();

        $receipt = DuesReceipt::sole();

        $this->assertSame('200000.00', $receipt->total_amount);
        $this->assertSame(6, $receipt->months_count);
        // Bulan-bulannya tetap lunas pada nilai tagihannya masing-masing.
        $this->assertSame(6, $member->duesRecords()->where('status', DuesStatus::Lunas)->count());
    }

    /**
     * Kolom total memakai titik ribuan agar mudah dibaca petugas. Titiknya harus
     * lepas sebelum divalidasi, jadi "175.000" tersimpan sebagai 175000 — bukan 175.
     */
    public function test_the_thousand_separator_is_stripped_before_the_total_is_saved(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->callAction('bayarIuran', data: [
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 1,
                'to_month' => 7,
                'total_amount' => '175.000',
                'received_by_name' => $admin->name,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('175000.00', DuesReceipt::sole()->total_amount);
    }

    public function test_receipt_records_and_prints_the_person_who_received_the_money(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Kantor']);
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->callAction('bayarIuran', data: [
                'member_id' => $member->id,
                'year' => 2026,
                'from_month' => 1,
                'to_month' => 2,
                'total_amount' => 50000,
                // Uang dititipkan lewat orang lain, jadi namanya diketik manual.
                'received_by_name' => 'Pak Ahmad (titipan)',
            ])
            ->assertHasNoActionErrors();

        $receipt = DuesReceipt::sole();

        $this->assertSame('Pak Ahmad (titipan)', $receipt->received_by_name);
        $this->assertSame('Pak Ahmad (titipan)', $receipt->receiverName());
        $this->assertStringContainsString('Pak Ahmad (titipan)', $this->renderReceipt($receipt));
    }

    public function test_receiver_falls_back_to_the_issuing_officer(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Bendahara Lim']);
        $receipt = DuesReceipt::factory()->create([
            'issued_by' => $admin->id,
            'received_by_name' => null,
        ]);

        $this->assertSame('Bendahara Lim', $receipt->receiverName());
        $this->assertStringContainsString('Bendahara Lim', $this->renderReceipt($receipt));
    }

    public function test_issued_receipts_are_listed_on_the_same_page_for_reprinting(): void
    {
        $admin = User::factory()->admin()->create();
        $receipts = DuesReceipt::factory()->count(3)->create();

        Livewire::actingAs($admin)
            ->test(PembayaranKantor::class)
            ->assertCanSeeTableRecords($receipts);
    }

    public function test_no_receipt_is_issued_when_every_month_was_already_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);
        $service = app(PaymentService::class);

        $service->payRange($member, 2026, 1, 3, $admin, CollectionMethod::Admin);
        $again = $service->payRangeAndIssueReceipt($member, 2026, 1, 3, $admin, CollectionMethod::Admin);

        $this->assertNull($again);
        $this->assertDatabaseCount('dues_receipts', 0);
    }

    public function test_receipt_covers_only_the_months_actually_paid_now(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->active()->create(['monthly_dues_amount' => 25000]);
        $service = app(PaymentService::class);

        // Januari–Februari sudah lunas sebelumnya; yang tercetak hanya Maret–Mei
        // supaya nominal di kertas sama dengan uang yang baru diterima.
        $service->payRange($member, 2026, 1, 2, $admin, CollectionMethod::Admin);
        $receipt = $service->payRangeAndIssueReceipt($member, 2026, 1, 5, $admin, CollectionMethod::Admin);

        $this->assertNotNull($receipt);
        $this->assertSame(3, $receipt->from_month);
        $this->assertSame(5, $receipt->to_month);
        $this->assertSame(3, $receipt->months_count);
        $this->assertSame('75000.00', $receipt->total_amount);
    }

    public function test_receipt_route_streams_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $receipt = DuesReceipt::factory()->create();

        $response = $this->actingAs($admin)->get(route('dues.receipt', $receipt));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_receipt_route_is_behind_auth(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->getName() === 'dues.receipt');

        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_note_states_member_period_and_amount_in_words(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Bendahara Lim']);
        $member = Member::factory()->active()->create([
            'name_indonesian' => 'Budi Santoso',
            'address' => 'Jl. Bontosua No. 1',
        ]);
        $receipt = DuesReceipt::factory()->create([
            'member_id' => $member->id,
            'period_year' => 2026,
            'from_month' => 1,
            'to_month' => 3,
            'months_count' => 3,
            'total_amount' => 75000,
            'issued_by' => $admin->id,
        ]);

        $html = $this->renderReceipt($receipt);

        $this->assertStringContainsString('TANDA TERIMA', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Jl. Bontosua No. 1', $html);
        $this->assertStringContainsString('PEMBAYARAN IURAN DARI BULAN', $html);
        $this->assertStringContainsString('Januari', $html);
        $this->assertStringContainsString('Maret', $html);
        $this->assertStringContainsString('75.000', $html);
        $this->assertStringContainsString('tujuh puluh lima ribu rupiah', $html);
        $this->assertStringContainsString('Bendahara Lim', $html);
        $this->assertStringContainsString($receipt->receipt_number, $html);
    }

    public function test_receipt_numbers_run_in_sequence_per_year(): void
    {
        $first = DuesReceipt::factory()->create(['issued_at' => '2026-01-05']);
        $second = DuesReceipt::factory()->create(['issued_at' => '2026-06-05']);

        $this->assertSame('IUR-2026-0001', $first->receipt_number);
        $this->assertSame('IUR-2026-0002', $second->receipt_number);
    }

    /**
     * Render nota tanpa lapisan PDF supaya isinya bisa diperiksa langsung.
     */
    private function renderReceipt(DuesReceipt $receipt): string
    {
        return view('pdf.dues-receipt', [
            'receipt' => $receipt->load(['member', 'issuedBy']),
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();
    }

    public function test_single_month_period_is_labelled_without_a_range(): void
    {
        $receipt = DuesReceipt::factory()->create([
            'period_year' => 2026,
            'from_month' => 4,
            'to_month' => 4,
            'months_count' => 1,
        ]);

        $this->assertSame('April 2026', $receipt->periodRangeLabel());
    }
}
