<?php

namespace App\Filament\Pages;

use App\Enums\CollectionMethod;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Support\PaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Pembayaran langsung di kantor (walk-in) untuk beberapa bulan sekaligus.
 * Berbeda dari kolektor lapangan, pembayaran ini ditangani admin sehingga
 * langsung LUNAS tanpa proses persetujuan. (§6.4)
 *
 * @property-read Schema $form
 */
class PembayaranKantor extends Page
{
    protected string $view = 'filament.pages.pembayaran-kantor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Pembayaran Kantor';

    protected static string|UnitEnum|null $navigationGroup = 'Iuran';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Pembayaran di Kantor';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'year' => (int) now()->year,
            'from_month' => (int) now()->month,
            'to_month' => (int) now()->month,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Bayar Iuran Beberapa Bulan')
                        ->description('Untuk anggota yang datang langsung membayar di kantor. Seluruh bulan terpilih akan langsung ditandai LUNAS.')
                        ->schema([
                            Select::make('member_id')
                                ->label('Anggota')
                                ->required()
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => Member::query()
                                    ->where('status', MemberStatus::Aktif)
                                    ->where(fn ($q) => $q
                                        ->where('name_indonesian', 'like', "%{$search}%")
                                        ->orWhere('name_pinyin', 'like', "%{$search}%")
                                        ->orWhere('name_hanzi', 'like', "%{$search}%")
                                        ->orWhere('member_number', 'like', "%{$search}%"))
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Member $m): array => [$m->id => $m->member_number.' — '.$m->displayName()])
                                    ->all())
                                ->getOptionLabelUsing(fn ($value): ?string => Member::find($value)?->displayName()),
                            Select::make('year')
                                ->label('Tahun')
                                ->options(self::yearOptions())
                                ->default((int) now()->year)
                                ->required(),
                            Select::make('from_month')
                                ->label('Dari Bulan')
                                ->options(self::monthOptions())
                                ->default((int) now()->month)
                                ->required()
                                ->live(),
                            Select::make('to_month')
                                ->label('Sampai Bulan')
                                ->options(self::monthOptions())
                                ->default((int) now()->month)
                                ->required()
                                ->rule(fn (Get $get): string => 'gte:'.((int) $get('from_month')))
                                ->validationMessages([
                                    'gte' => 'Bulan akhir tidak boleh sebelum bulan awal.',
                                ]),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Tandai Lunas')
                                ->icon('heroicon-o-check-circle')
                                ->color('success')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $member = Member::findOrFail($data['member_id']);

        $count = app(PaymentService::class)->payRange(
            $member,
            (int) $data['year'],
            (int) $data['from_month'],
            (int) $data['to_month'],
            auth()->user(),
            CollectionMethod::Admin,
        );

        Notification::make()
            ->success()
            ->title($count > 0
                ? $count.' bulan ditandai LUNAS untuk '.$member->displayName().'.'
                : 'Tidak ada bulan baru yang dilunasi (semua sudah lunas).')
            ->send();

        $this->form->fill([
            'year' => (int) $data['year'],
            'from_month' => (int) now()->month,
            'to_month' => (int) now()->month,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function monthOptions(): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = Carbon::create(null, $m, 1)->translatedFormat('F');
        }

        return $months;
    }

    /**
     * @return array<int, int>
     */
    protected static function yearOptions(): array
    {
        $current = (int) now()->year;
        $years = [];
        for ($y = $current - 2; $y <= $current + 1; $y++) {
            $years[$y] = $y;
        }

        return $years;
    }
}
