<?php

namespace App\Filament\Pages;

use App\Enums\CollectionMethod;
use App\Enums\MemberStatus;
use App\Models\DuesReceipt;
use App\Models\Member;
use App\Support\PaymentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Pembayaran langsung di kantor (walk-in) untuk beberapa bulan sekaligus.
 * Berbeda dari kolektor lapangan, pembayaran ini ditangani admin sehingga
 * langsung LUNAS tanpa proses persetujuan. (§6.4)
 *
 * Arsip nota menempel di halaman yang sama, bukan menu terpisah: notanya lahir
 * dari tombol di header, dan yang dicari petugas biasanya nota yang baru saja
 * dibuat — mencetak ulang jadi tidak perlu pindah menu.
 *
 * Formulir pembayaran sengaja disimpan di dalam modal: sebagian besar kunjungan
 * ke halaman ini hanya untuk mencari dan mencetak ulang nota, sehingga arsipnya
 * yang layak tampil lebih dulu.
 */
class PembayaranKantor extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.pembayaran-kantor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Pembayaran Kantor';

    protected static string|UnitEnum|null $navigationGroup = 'Iuran';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Pembayaran di Kantor';

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->payDuesAction(),
        ];
    }

    /**
     * Isi ulang kolom total dengan tagihan bulan terpilih setiap anggota atau
     * periodenya berubah — sekadar angka awal, petugas tetap boleh menimpanya.
     */
    protected static function suggestTotal(Get $get, Set $set): void
    {
        $member = $get('member_id') ? Member::find($get('member_id')) : null;

        if ($member === null) {
            return;
        }

        $set('total_amount', app(PaymentService::class)->outstandingTotal(
            $member,
            (int) $get('year'),
            (int) $get('from_month'),
            (int) $get('to_month'),
        ));
    }

    /**
     * Formulir pembayaran walk-in, dibuka lewat tombol di header halaman.
     */
    public function payDuesAction(): Action
    {
        return Action::make('bayarIuran')
            ->label('Bayar Iuran')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->modalHeading('Bayar Iuran Beberapa Bulan')
            ->modalDescription('Untuk anggota yang datang langsung membayar di kantor. Seluruh bulan terpilih akan langsung ditandai LUNAS.')
            ->modalSubmitActionLabel('Tandai Lunas')
            ->modalWidth(Width::ThreeExtraLarge)
            // Diisi ulang setiap modal dibuka, jadi petugas yang melayani beberapa
            // anggota berturut-turut selalu mulai dari periode berjalan.
            ->fillForm(fn (): array => [
                'year' => (int) now()->year,
                'from_month' => (int) now()->month,
                'to_month' => (int) now()->month,
                'received_by_name' => auth()->user()?->name,
            ])
            ->schema([
                Grid::make(3)
                    ->schema([
                        Select::make('member_id')
                            ->label('Anggota')
                            ->required()
                            ->searchable()
                            ->live()
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
                            ->getOptionLabelUsing(fn ($value): ?string => Member::find($value)?->displayName())
                            ->afterStateUpdated(self::suggestTotal(...))
                            ->columnSpanFull(),
                        Select::make('year')
                            ->label('Tahun')
                            ->options(self::yearOptions())
                            ->default((int) now()->year)
                            ->required()
                            ->live()
                            ->afterStateUpdated(self::suggestTotal(...)),
                        Select::make('from_month')
                            ->label('Dari Bulan')
                            ->options(self::monthOptions())
                            ->default((int) now()->month)
                            ->required()
                            ->live()
                            ->afterStateUpdated(self::suggestTotal(...)),
                        Select::make('to_month')
                            ->label('Sampai Bulan')
                            ->options(self::monthOptions())
                            ->default((int) now()->month)
                            ->required()
                            ->live()
                            ->rule(fn (Get $get): string => 'gte:'.((int) $get('from_month')))
                            ->validationMessages([
                                'gte' => 'Bulan akhir tidak boleh sebelum bulan awal.',
                            ])
                            ->afterStateUpdated(self::suggestTotal(...)),
                        TextInput::make('total_amount')
                            ->label('Total Uang Diterima')
                            ->helperText('Terisi otomatis dari tagihan bulan terpilih, tetapi boleh diubah sesuai uang yang benar-benar diterima. Angka inilah yang tercetak di nota.')
                            // Titik ribuan dipasang saat mengetik supaya nominal besar
                            // mudah dibaca sekali lihat; titiknya dilucuti lagi sebelum
                            // divalidasi dan disimpan.
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters('.')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->required(),
                        TextInput::make('received_by_name')
                            ->label('Yang Menerima')
                            ->helperText('Nama yang tercetak di nota. Ubah bila uangnya dititipkan dan diterima orang lain.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                    ]),
            ])
            ->action($this->payDues(...));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function payDues(array $data): void
    {
        $member = Member::findOrFail($data['member_id']);

        $receipt = app(PaymentService::class)->payRangeAndIssueReceipt(
            $member,
            (int) $data['year'],
            (int) $data['from_month'],
            (int) $data['to_month'],
            auth()->user(),
            CollectionMethod::Admin,
            (float) $data['total_amount'],
            $data['received_by_name'],
        );

        // Nota hanya terbit bila ada bulan yang benar-benar baru dilunasi, jadi
        // anggota tidak pernah menerima kertas bernominal nol.
        if ($receipt === null) {
            Notification::make()
                ->warning()
                ->title('Tidak ada bulan baru yang dilunasi (semua sudah lunas).')
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title($receipt->months_count.' bulan ditandai LUNAS untuk '.$member->displayName().'.')
                ->body('Nota '.$receipt->receipt_number.' — total Rp '.number_format($receipt->total_amount, 0, ',', '.'))
                ->actions([
                    Action::make('printReceipt')
                        ->label('Cetak Tanda Terima')
                        ->url(route('dues.receipt', $receipt), shouldOpenInNewTab: true),
                ])
                ->persistent()
                ->send();
        }
    }

    /**
     * Arsip nota yang sudah terbit — baca-saja, hanya untuk mencetak ulang.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(DuesReceipt::query()->with(['member', 'issuedBy']))
            ->heading('Nota yang Sudah Terbit')
            ->description('Cari nota lama di sini bila perlu dicetak ulang.')
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('No. Nota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('member.name_indonesian')
                    ->label('Anggota')
                    ->description(fn (DuesReceipt $record): ?string => $record->member?->member_number)
                    ->searchable(['name_indonesian', 'name_pinyin', 'name_hanzi']),
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (DuesReceipt $record): string => $record->periodRangeLabel()),
                TextColumn::make('months_count')
                    ->label('Jml Bulan')
                    ->alignCenter(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('received_by_name')
                    ->label('Yang Menerima')
                    ->state(fn (DuesReceipt $record): ?string => $record->receiverName())
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('period_year')
                    ->label('Tahun')
                    ->options(fn (): array => DuesReceipt::query()
                        ->distinct()
                        ->orderByDesc('period_year')
                        ->pluck('period_year', 'period_year')
                        ->all()),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('Cetak Ulang')
                    ->icon('heroicon-o-printer')
                    ->url(fn (DuesReceipt $record): string => route('dues.receipt', $record))
                    ->openUrlInNewTab(),
                $this->cancelReceiptAction(),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    /**
     * Membatalkan pembayaran kantor yang salah dicatat. Dibatasi Super Admin
     * karena ini menghapus catatan uang: notanya hilang dan bulan-bulan yang
     * dilunasinya kembali menjadi tagihan, sehingga Laporan Keuangan ikut turun.
     */
    protected function cancelReceiptAction(): Action
    {
        return Action::make('cancelReceipt')
            ->label('Hapus')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Hapus Nota & Batalkan Pembayaran')
            ->modalDescription(fn (DuesReceipt $record): string => sprintf(
                'Nota %s atas nama %s (%s, %s bulan, Rp %s) akan dihapus, dan seluruh bulan yang dilunasinya kembali menjadi BELUM BAYAR. Uangnya juga keluar dari Laporan Keuangan. Tindakan ini tidak bisa dibatalkan.',
                $record->receipt_number,
                $record->member?->displayName() ?? '—',
                $record->periodRangeLabel(),
                $record->months_count,
                number_format((float) $record->total_amount, 0, ',', '.'),
            ))
            ->modalSubmitActionLabel('Hapus & Batalkan')
            ->action(function (DuesReceipt $record): void {
                $number = $record->receipt_number;
                $reverted = app(PaymentService::class)->cancelReceipt($record, auth()->user());

                Notification::make()
                    ->success()
                    ->title('Nota '.$number.' dihapus.')
                    ->body($reverted.' bulan dikembalikan menjadi belum bayar.')
                    ->send();
            });
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
