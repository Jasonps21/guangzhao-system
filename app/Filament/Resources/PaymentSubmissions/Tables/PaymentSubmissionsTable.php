<?php

namespace App\Filament\Resources\PaymentSubmissions\Tables;

use App\Enums\SubmissionStatus;
use App\Models\PaymentSubmission;
use App\Support\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('member.name_indonesian')
                    ->label('Anggota')
                    ->description(fn (PaymentSubmission $record): ?string => $record->member?->member_number)
                    ->searchable(['name_indonesian', 'name_pinyin', 'name_hanzi']),
                TextColumn::make('collector.name')
                    ->label('Kolektor')
                    ->placeholder('—'),
                TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (PaymentSubmission $record): string => $record->periodRangeLabel()),
                TextColumn::make('months')
                    ->label('Jml Bulan')
                    ->state(fn (PaymentSubmission $record): int => $record->monthsCount())
                    ->alignCenter(),
                TextColumn::make('total_amount')
                    ->label('Total Setoran')
                    ->money('IDR'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('reviewedBy.name')
                    ->label('Diverifikasi oleh')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SubmissionStatus::class)
                    ->default(SubmissionStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PaymentSubmission $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Setujui setoran?')
                    ->modalDescription('Pastikan uang setoran sudah diterima. Seluruh bulan akan ditandai LUNAS.')
                    ->action(function (PaymentSubmission $record): void {
                        app(PaymentService::class)->approveSubmission($record, auth()->user());

                        Notification::make()->success()->title('Setoran disetujui — bulan terkait kini LUNAS.')->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PaymentSubmission $record): bool => $record->isPending())
                    ->schema([
                        Textarea::make('review_notes')
                            ->label('Alasan penolakan')
                            ->placeholder('mis. uang setoran belum diterima / nominal tidak sesuai')
                            ->required(),
                    ])
                    ->action(function (PaymentSubmission $record, array $data): void {
                        app(PaymentService::class)->rejectSubmission($record, auth()->user(), $data['review_notes']);

                        Notification::make()->warning()->title('Setoran ditolak — bulan terkait kembali BELUM BAYAR.')->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
