<?php

namespace Tests\Feature;

use App\Enums\SocialFundDirection;
use App\Enums\SocialFundForm;
use App\Filament\Resources\SocialFundRecords\Pages\CreateSocialFundRecord;
use App\Models\OrganizationSetting;
use App\Models\SocialFundRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocialFundReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_route_streams_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $record = SocialFundRecord::factory()->create();

        $response = $this->actingAs($admin)->get(route('social-fund.receipt', $record));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_receipt_route_is_behind_auth(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route) => $route->getName() === 'social-fund.receipt');

        $this->assertContains('auth', $route->gatherMiddleware());
    }

    public function test_incoming_note_names_the_donor_as_the_source(): void
    {
        $record = SocialFundRecord::factory()->incoming()->create([
            'party_name' => 'Toko Sumber Rejeki',
            'party_address' => 'Jl. Sulawesi 12',
            'amount' => 2500000,
            'purpose' => 'Bakti sosial',
        ]);

        $html = $this->renderReceipt($record);

        $this->assertStringContainsString('TANDA TERIMA', $html);
        // Kerangka nota sama dengan lembar aslinya; hanya kalimat kerjanya yang
        // berbalik arah untuk sumbangan yang masuk.
        $this->assertStringContainsString('Yang bertanda tangan dibawah ini:', $html);
        $this->assertStringContainsString('Telah menyerahkan dana sosial kepada', $html);
        $this->assertStringContainsString('Toko Sumber Rejeki', $html);
        $this->assertStringContainsString('Jl. Sulawesi 12', $html);
        $this->assertStringContainsString('2.500.000', $html);
        $this->assertStringContainsString('dua juta lima ratus ribu rupiah', $html);
        $this->assertStringContainsString('Bakti sosial', $html);
    }

    public function test_outgoing_note_states_the_organisation_as_the_source(): void
    {
        $organization = OrganizationSetting::current();
        $record = SocialFundRecord::factory()->outgoing()->create([
            'party_name' => 'Ibu Ani',
            'amount' => 500000,
        ]);

        $html = $this->renderReceipt($record);

        $this->assertStringContainsString('Yang bertanda tangan dibawah ini:', $html);
        $this->assertStringContainsString('Telah terima dana sosial', $html);
        $this->assertStringContainsString($organization->name, $html);
        // Pada dana keluar, penerimanya ikut tanda tangan di kolom "Yang Menerima".
        $this->assertStringContainsString('Yang Menerima,', $html);
        $this->assertSame(2, substr_count($html, 'Ibu Ani'));
    }

    public function test_goods_donation_prints_the_item_instead_of_a_nominal(): void
    {
        $record = SocialFundRecord::factory()->incoming()->inGoods('20 dus mie instan')->create();

        $html = $this->renderReceipt($record);

        $this->assertStringContainsString('sumbangan barang', $html);
        $this->assertStringContainsString('20 dus mie instan', $html);
        $this->assertStringNotContainsString('Sejumlah Rp', $html);
        $this->assertStringNotContainsString('Terbilang:', $html);
    }

    public function test_goods_donation_may_still_carry_an_estimated_value(): void
    {
        $record = SocialFundRecord::factory()->incoming()->inGoods()->create(['amount' => 750000]);

        $html = $this->renderReceipt($record);

        $this->assertStringContainsString('Senilai Rp', $html);
        $this->assertStringContainsString('750.000', $html);
        $this->assertStringContainsString('tujuh ratus lima puluh ribu rupiah', $html);
    }

    public function test_receipt_numbers_run_in_sequence_per_year(): void
    {
        $first = SocialFundRecord::factory()->create(['transacted_at' => '2026-02-10']);
        $second = SocialFundRecord::factory()->create(['transacted_at' => '2026-09-01']);

        $this->assertSame('BTN-2026-0001', $first->receipt_number);
        $this->assertSame('BTN-2026-0002', $second->receipt_number);
    }

    public function test_direction_and_form_are_cast_to_enums(): void
    {
        $record = SocialFundRecord::factory()->outgoing()->inGoods()->create();

        $this->assertSame(SocialFundDirection::Keluar, $record->refresh()->direction);
        $this->assertSame(SocialFundForm::Barang, $record->fund_form);
        $this->assertTrue($record->isInGoods());
        $this->assertFalse($record->isIncoming());
    }

    public function test_admin_can_record_an_incoming_donation_from_the_panel(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateSocialFundRecord::class)
            ->fillForm([
                'direction' => SocialFundDirection::Masuk->value,
                'fund_form' => SocialFundForm::Uang->value,
                'party_name' => 'Toko Sumber Rejeki',
                'party_address' => 'Jl. Sulawesi 12',
                'amount' => 2500000,
                'purpose' => 'Bakti sosial',
                'transacted_at' => '2026-07-27',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = SocialFundRecord::sole();

        $this->assertSame(SocialFundDirection::Masuk, $record->direction);
        $this->assertSame('2500000.00', $record->amount);
        $this->assertSame($admin->id, $record->recorded_by);
        $this->assertSame('BTN-2026-0001', $record->receipt_number);
    }

    public function test_goods_donation_requires_a_description_instead_of_an_amount(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateSocialFundRecord::class)
            ->fillForm([
                'direction' => SocialFundDirection::Masuk->value,
                'fund_form' => SocialFundForm::Barang->value,
                'party_name' => 'Ibu Ani',
                'item_description' => null,
                'amount' => null,
                'transacted_at' => '2026-07-27',
            ])
            ->call('create')
            ->assertHasFormErrors(['item_description' => 'required']);

        Livewire::actingAs($admin)
            ->test(CreateSocialFundRecord::class)
            ->fillForm([
                'direction' => SocialFundDirection::Masuk->value,
                'fund_form' => SocialFundForm::Barang->value,
                'party_name' => 'Ibu Ani',
                'item_description' => '20 dus mie instan',
                'amount' => null,
                'transacted_at' => '2026-07-27',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('20 dus mie instan', SocialFundRecord::sole()->item_description);
    }

    private function renderReceipt(SocialFundRecord $record): string
    {
        return view('pdf.social-fund-receipt', [
            'record' => $record,
            'organization' => OrganizationSetting::current(),
            'logo' => null,
        ])->render();
    }
}
