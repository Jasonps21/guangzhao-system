<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Enums\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityNarrator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        $narrator = app(ActivityNarrator::class);

        return $table
            // Tanpa ini tiap baris memicu query untuk pelaku dan objeknya.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Pengguna')
                    // Ekspor terjadwal dan perintah terminal tidak punya pelaku.
                    ->placeholder('Sistem'),
                TextColumn::make('description')
                    ->label('Kegiatan')
                    ->state(fn (ActivityLog $record): string => $narrator->title($record))
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Objek')
                    ->state(fn (ActivityLog $record): ?string => $narrator->subject($record))
                    ->placeholder('—')
                    ->wrap()
                    /*
                     * Nama anggota tidak tersimpan di kolom mana pun — hanya di
                     * dalam properti. Pencarian diarahkan ke sana supaya "Ho Li
                     * Cien" tetap ketemu.
                     */
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('properties', 'like', '%'.$search.'%')),
                TextColumn::make('log_name')
                    ->label('Golongan')
                    ->badge()
                    ->state(fn (ActivityLog $record): ActivityCategory => $record->category())
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Golongan')
                    ->options(ActivityCategory::class),
                /*
                 * Bukan ->relationship(): `causer` polimorfik, jadi tidak bisa
                 * di-join. Daftar penggunanya diambil langsung.
                 */
                SelectFilter::make('causer_id')
                    ->label('Pengguna')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('subject_type')
                    ->label('Jenis objek')
                    ->options(fn (): array => ActivityLog::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->map(fn (string $type): string => $narrator->subjectLabel($type))
                        ->all()),
                Filter::make('rentang_waktu')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['dari'] ?? null;
                        $until = $data['sampai'] ?? null;

                        if (! $from && ! $until) {
                            return null;
                        }

                        return 'Waktu: '
                            .($from ? Carbon::parse($from)->translatedFormat('d M Y') : 'awal')
                            .' — '
                            .($until ? Carbon::parse($until)->translatedFormat('d M Y') : 'sekarang');
                    }),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ActivityLog $record): string => $narrator->title($record))
                    ->modalContent(fn (ActivityLog $record): View => view('filament.activity-log-detail', [
                        'log' => $record,
                        'narrator' => $narrator,
                    ]))
                    // Tidak ada yang bisa disimpan dari sini — hanya dibaca.
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            /*
             * Diurutkan menurut id, bukan created_at: beberapa kegiatan dalam
             * detik yang sama harus tetap tampil sesuai urutan terjadinya.
             */
            ->defaultSort('id', 'desc');
    }
}
