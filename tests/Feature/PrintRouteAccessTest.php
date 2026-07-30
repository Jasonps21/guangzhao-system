<?php

namespace Tests\Feature;

use App\Models\CondolencePayment;
use App\Models\DuesReceipt;
use App\Models\Member;
use App\Models\SocialFundRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seluruh route cetak PDF beserta record contoh yang dibutuhkan.
     *
     * @return list<string>
     */
    private function printUrls(): array
    {
        $member = Member::factory()->active()->create();
        $condolence = CondolencePayment::factory()->create();
        $socialFund = SocialFundRecord::factory()->create();
        $receipt = DuesReceipt::factory()->create();

        return [
            route('dues.coupons', ['year' => now()->year, 'month' => now()->month]),
            route('members.list'),
            route('members.card', $member),
            route('condolence.receipt', $condolence),
            route('condolence.handover', $condolence),
            route('social-fund.receipt', $socialFund),
            route('dues.receipt', $receipt),
        ];
    }

    public function test_collectors_cannot_reach_any_print_route(): void
    {
        $collector = User::factory()->collector()->create();

        foreach ($this->printUrls() as $url) {
            $this->actingAs($collector)->get($url)->assertForbidden();
        }
    }

    public function test_deactivated_managers_cannot_reach_any_print_route(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => false]);

        foreach ($this->printUrls() as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }

    /**
     * Tamu tetap tidak pernah menerima PDF. Statusnya belum tentu redirect karena
     * aplikasi tidak punya route bernama `login`, jadi yang diuji adalah hasilnya:
     * tidak ada dokumen yang keluar.
     */
    public function test_guests_never_receive_a_pdf(): void
    {
        foreach ($this->printUrls() as $url) {
            $response = $this->get($url);

            $this->assertNotSame(200, $response->getStatusCode(), $url.' tidak boleh dilayani untuk tamu');
            $this->assertNotSame('application/pdf', $response->headers->get('content-type'));
        }
    }

    public function test_admins_can_still_print(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ($this->printUrls() as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_super_admins_can_still_print(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        foreach ($this->printUrls() as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
        }
    }

    /**
     * Halaman lapangan kolektor tidak boleh ikut terkunci oleh middleware baru.
     */
    public function test_collector_field_pages_remain_reachable(): void
    {
        $collector = User::factory()->collector()->create();

        $this->actingAs($collector)->get(route('kolektor.groups'))->assertOk();
    }
}
