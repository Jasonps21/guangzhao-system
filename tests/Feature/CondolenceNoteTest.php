<?php

namespace Tests\Feature;

use App\Filament\Resources\CondolencePayments\Pages\CreateCondolencePayment;
use App\Models\CondolencePayment;
use App\Models\Member;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CondolenceNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_route_streams_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = CondolencePayment::factory()->create();

        $response = $this->actingAs($admin)->get(route('condolence.receipt', $payment));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_handover_route_streams_a_pdf(): void
    {
        $admin = User::factory()->admin()->create();
        $payment = CondolencePayment::factory()->create();

        $response = $this->actingAs($admin)->get(route('condolence.handover', $payment));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_note_routes_are_behind_auth(): void
    {
        foreach (['condolence.receipt', 'condolence.handover'] as $name) {
            $route = collect(app('router')->getRoutes())
                ->first(fn ($route) => $route->getName() === $name);

            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_receipt_is_signed_by_the_family_and_states_the_amount(): void
    {
        $payment = CondolencePayment::factory()->create([
            'recipient_name' => 'Budi Santoso',
            'recipient_member_number' => 'GZ-12345',
            'amount' => 1000000,
        ]);

        $html = $this->renderNote($payment, 'terima');

        $this->assertStringContainsString('TANDA TERIMA', $html);
        $this->assertStringContainsString('TELAH TERIMA UANG DUKA SEBESAR', $html);
        $this->assertStringContainsString('Rp 1.000.000', $html);
        $this->assertStringContainsString('SATU JUTA RUPIAH', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('GZ-12345', $html);
        // Ditandatangani keluarga penerima, bukan pengurus.
        $this->assertStringContainsString('家属签收,', $html);
        $this->assertStringContainsString('PENERIMA,', $html);
        $this->assertStringNotContainsString('福利部', $html);
    }

    public function test_handover_is_signed_by_the_welfare_department(): void
    {
        $payment = CondolencePayment::factory()->create(['amount' => 1000000]);

        $html = $this->renderNote($payment, 'pemberian');

        $this->assertStringContainsString('TANDA PEMBERIAN', $html);
        $this->assertStringContainsString('TELAH MEMBERI UANG DUKA SEBESAR', $html);
        $this->assertStringContainsString('福利部,', $html);
        $this->assertStringNotContainsString('家属签收', $html);
        // 交给 pada lembar pemberian, 交來 pada lembar terima.
        $this->assertStringContainsString('交给', $html);
    }

    public function test_amount_is_also_printed_in_chinese_capitals(): void
    {
        $payment = CondolencePayment::factory()->create(['amount' => 1000000]);

        $this->assertStringContainsString('壹佰万盾正', $this->renderNote($payment, 'terima'));
    }

    public function test_member_data_is_copied_onto_the_note_when_left_blank(): void
    {
        $member = Member::factory()->create([
            'member_number' => 'GZ-77777',
            'name_indonesian' => 'Lestiana Gosali',
            'name_hanzi' => '吴素兰',
        ]);

        $payment = CondolencePayment::create([
            'member_id' => $member->id,
            'recipient_name' => '',
            'amount' => 500000,
            'given_at' => now(),
        ]);

        $this->assertSame('Lestiana Gosali', $payment->recipient_name);
        $this->assertSame('GZ-77777', $payment->recipient_member_number);
        $this->assertSame('吴素兰', $payment->deceased_name_hanzi);
    }

    public function test_non_member_recipients_keep_the_typed_name(): void
    {
        $payment = CondolencePayment::factory()->withoutMember()->create([
            'recipient_name' => 'Keluarga Almarhum Tan',
        ]);

        $this->assertNull($payment->member_id);
        $this->assertSame('Keluarga Almarhum Tan', $payment->recipient_name);
    }

    public function test_receipt_numbers_run_in_sequence_per_year(): void
    {
        $first = CondolencePayment::factory()->create(['given_at' => '2026-03-01']);
        $second = CondolencePayment::factory()->create(['given_at' => '2026-11-20']);
        // Deret nomor mengikuti tahun transaksi, bukan tahun input.
        $otherYear = CondolencePayment::factory()->create(['given_at' => '2027-01-05']);

        $this->assertSame('DUK-2026-0001', $first->receipt_number);
        $this->assertSame('DUK-2026-0002', $second->receipt_number);
        $this->assertSame('DUK-2027-0001', $otherYear->receipt_number);
    }

    public function test_admin_can_record_a_condolence_payment_from_the_panel(): void
    {
        $admin = User::factory()->admin()->create();
        $member = Member::factory()->create();

        Livewire::actingAs($admin)
            ->test(CreateCondolencePayment::class)
            ->fillForm([
                'member_id' => $member->id,
                'recipient_name' => 'Keluarga Budi',
                'recipient_member_number' => $member->member_number,
                'amount' => 1000000,
                'given_at' => '2026-07-27',
                'escort_name' => 'Hendra',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $payment = CondolencePayment::sole();

        $this->assertSame('Keluarga Budi', $payment->recipient_name);
        $this->assertSame('1000000.00', $payment->amount);
        // Petugas pencatat direkam otomatis, tidak diketik di formulir.
        $this->assertSame($admin->id, $payment->recorded_by);
        $this->assertSame('DUK-2026-0001', $payment->receipt_number);
    }

    public function test_recipient_name_and_amount_are_required(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreateCondolencePayment::class)
            ->fillForm([
                'recipient_name' => null,
                'amount' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'recipient_name' => 'required',
                'amount' => 'required',
            ]);
    }

    /**
     * Render nota tanpa lapisan PDF supaya isinya bisa diperiksa langsung.
     */
    private function renderNote(CondolencePayment $payment, string $variant): string
    {
        return view('pdf.condolence-note', [
            'payment' => $payment,
            'organization' => OrganizationSetting::current(),
            'variant' => $variant,
        ])->render();
    }
}
