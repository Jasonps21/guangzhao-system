<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gerbang masuk tunggal: semua peran memakai satu halaman, dan sistem yang
 * menentukan tujuan. Tidak boleh ada lagi kondisi "kata sandi benar tapi
 * ditolak karena salah pintu".
 */
class LoginGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_collector_signing_in_lands_on_the_field_page(): void
    {
        $collector = User::factory()->collector()->create(['username' => 'daya']);

        Livewire::test(Login::class)
            ->set('username', 'daya')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('kolektor.groups'));

        $this->assertAuthenticatedAs($collector);
    }

    public function test_manager_signing_in_lands_on_the_admin_panel(): void
    {
        $admin = User::factory()->create(['username' => 'bendahara']);

        Livewire::test(Login::class)
            ->set('username', 'bendahara')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('filament.admin.pages.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * Inti perbaikannya: dulu kolektor yang membuka /admin/login ditolak walau
     * kata sandinya benar. Sekarang pintunya sama untuk semua orang.
     */
    public function test_collector_is_not_rejected_for_using_the_same_door_as_admin(): void
    {
        User::factory()->collector()->create(['username' => 'daya']);

        Livewire::test(Login::class)
            ->set('username', 'daya')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_email_still_works_so_existing_habits_do_not_break(): void
    {
        User::factory()->collector()->create([
            'username' => 'daya',
            'email' => 'kolektor@guangzhao.test',
        ]);

        Livewire::test(Login::class)
            ->set('username', 'kolektor@guangzhao.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_username_is_matched_ignoring_stray_spaces(): void
    {
        User::factory()->collector()->create(['username' => 'daya']);

        Livewire::test(Login::class)
            ->set('username', '  daya  ')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->collector()->create(['username' => 'daya']);

        Livewire::test(Login::class)
            ->set('username', 'daya')
            ->set('password', 'salah')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_get_in(): void
    {
        User::factory()->collector()->create([
            'username' => 'daya',
            'is_active' => false,
        ]);

        Livewire::test(Login::class)
            ->set('username', 'daya')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();
    }

    public function test_repeated_failures_are_rate_limited(): void
    {
        User::factory()->collector()->create(['username' => 'daya']);

        foreach (range(1, 5) as $ignored) {
            Livewire::test(Login::class)
                ->set('username', 'daya')
                ->set('password', 'salah')
                ->call('login')
                ->assertHasErrors('username');
        }

        // Percobaan ke-6 ditolak lebih dulu oleh throttle, walau kata sandinya benar.
        Livewire::test(Login::class)
            ->set('username', 'daya')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('username');

        $this->assertGuest();

        RateLimiter::clear('daya|127.0.0.1');
    }

    public function test_old_login_urls_redirect_into_the_single_gate(): void
    {
        $this->get('/kolektor/login')->assertRedirect('/masuk');
        $this->get('/admin/login')->assertRedirect('/masuk');
    }

    public function test_guest_visiting_the_admin_panel_is_sent_to_the_gate(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_already_signed_in_user_is_moved_along_instead_of_seeing_the_form(): void
    {
        $collector = User::factory()->collector()->create(['username' => 'daya']);

        Auth::login($collector);

        Livewire::test(Login::class)->assertRedirect(route('kolektor.groups'));
    }
}
