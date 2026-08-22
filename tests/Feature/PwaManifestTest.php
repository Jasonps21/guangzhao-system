<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manifest PWA dipasang pada layout gerbang masuk, jadi siapa pun — kolektor
 * maupun pengurus — bisa menekan "Pasang" dari halaman login. Karena itu
 * aplikasi terpasangnya tidak boleh dikunci ke satu peran: dulu start_url-nya
 * menunjuk langsung ke mode kolektor, sehingga Super Admin yang memasang
 * aplikasi selalu mendarat di halaman kolektor setiap kali membukanya kembali.
 */
class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function manifest(): array
    {
        return $this->get(route('manifest'))->assertOk()->json();
    }

    public function test_installed_app_starts_at_the_gate_that_dispatches_by_role(): void
    {
        $this->assertSame(route('login'), $this->manifest()['start_url']);
    }

    /**
     * Cakupan harus meliputi seluruh aplikasi. Bila hanya '/kolektor', panel
     * admin dianggap di luar aplikasi dan dibuka di tab browser terpisah.
     */
    public function test_scope_covers_both_the_admin_panel_and_the_field_mode(): void
    {
        $this->assertSame('/', $this->manifest()['scope']);
    }

    /**
     * Identitas aplikasi dikunci ke start_url lama supaya ponsel kolektor yang
     * sudah memasang aplikasi menerima manifest baru ini sebagai pembaruan,
     * bukan sebagai aplikasi lain.
     */
    public function test_app_identity_stays_the_same_for_phones_that_already_installed(): void
    {
        $this->assertSame('/kolektor', $this->manifest()['id']);
    }

    public function test_old_manifest_url_still_resolves(): void
    {
        $this->get('/kolektor/manifest.webmanifest')->assertRedirect(route('manifest'));
    }

    /**
     * Inti laporan bug: Super Admin membuka kembali aplikasi terpasang dan
     * malah mendarat di halaman kolektor.
     */
    public function test_manager_reopening_the_installed_app_lands_on_the_admin_dashboard(): void
    {
        $manager = User::factory()->superAdmin()->create();

        $this->actingAs($manager)
            ->get($this->manifest()['start_url'])
            ->assertRedirect(route('filament.admin.pages.dashboard'));
    }

    public function test_collector_reopening_the_installed_app_lands_on_the_field_page(): void
    {
        $collector = User::factory()->collector()->create();

        $this->actingAs($collector)
            ->get($this->manifest()['start_url'])
            ->assertRedirect(route('kolektor.groups'));
    }
}
