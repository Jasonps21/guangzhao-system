<?php

namespace Tests\Feature;

use App\Filament\Pages\KonfigurasiOrganisasi;
use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class KonfigurasiOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_page_loads_with_current_settings(): void
    {
        Livewire::test(KonfigurasiOrganisasi::class)
            ->assertOk()
            ->assertSchemaStateSet([
                'name' => 'PERKUMPULAN SOSIAL GUANG ZHAO',
                'name_hanzi' => '印尼锡江廣肇友好同乡会',
            ]);
    }

    public function test_settings_can_be_updated(): void
    {
        Livewire::test(KonfigurasiOrganisasi::class)
            ->fillForm([
                'name' => 'Perkumpulan Baru',
                'name_hanzi' => '新会',
                'chairman_name' => 'Budi',
                'treasurer_name' => 'Sinta',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $setting = OrganizationSetting::current();
        $this->assertSame('Perkumpulan Baru', $setting->name);
        $this->assertSame('新会', $setting->name_hanzi);
        $this->assertSame('Budi', $setting->chairman_name);
        $this->assertSame('Sinta', $setting->treasurer_name);
    }

    public function test_logo_can_be_uploaded_and_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        Livewire::test(KonfigurasiOrganisasi::class)
            ->fillForm([
                'name' => 'Perkumpulan Baru',
                'name_hanzi' => '新会',
                'logo_path' => UploadedFile::fake()->image('logo.png', 300, 300),
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $logoPath = OrganizationSetting::current()->logo_path;

        $this->assertNotNull($logoPath);
        $this->assertStringStartsWith('organization/', $logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_current_returns_a_single_row(): void
    {
        OrganizationSetting::current();
        OrganizationSetting::current();

        $this->assertSame(1, OrganizationSetting::query()->count());
    }
}
