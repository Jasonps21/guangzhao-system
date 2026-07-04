<?php

namespace App\Filament\Pages;

use App\Models\OrganizationSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class KonfigurasiOrganisasi extends Page
{
    protected string $view = 'filament.pages.konfigurasi-organisasi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Konfigurasi Organisasi';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $title = 'Konfigurasi Organisasi';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getRecord()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Identitas pada Kupon Iuran')
                        ->description('Teks ini dicetak di bagian atas dan bawah kupon iuran.')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Organisasi')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('name_hanzi')
                                ->label('Nama Mandarin (汉字)')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('contact_line')
                                ->label('Baris Alamat / Kontak')
                                ->maxLength(255),
                            TextInput::make('chairman_name')
                                ->label('Nama Ketua')
                                ->maxLength(255),
                            TextInput::make('treasurer_name')
                                ->label('Nama Bendahara')
                                ->maxLength(255),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->getRecord()->update($this->form->getState());

        Notification::make()
            ->success()
            ->title('Konfigurasi tersimpan.')
            ->send();
    }

    public function getRecord(): OrganizationSetting
    {
        return OrganizationSetting::current();
    }
}
