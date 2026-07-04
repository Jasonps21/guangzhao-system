<x-filament-panels::page>
    {{-- Filter periode & aksi --}}
    <x-filament::section>
        <x-slot name="heading">Periode Laporan</x-slot>
        <x-slot name="description">Pilih bulan dan tahun untuk menampilkan rekap iuran.</x-slot>

        <form wire:submit.prevent
              style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:1rem;">
            <div style="flex:0 1 16rem; min-width:12rem;">
                <x-filament::input.wrapper prefix-icon="heroicon-o-calendar-days">
                    <x-slot name="prefix">Periode</x-slot>
                    <x-filament::input
                        type="month"
                        wire:model.live="period"
                        min="2023-01"
                    />
                </x-filament::input.wrapper>
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem; margin-inline-start:auto;">
                <span wire:loading wire:target="period">
                    <x-filament::loading-indicator style="height:1.25rem; width:1.25rem;" />
                </span>
                <x-filament::button
                    tag="a"
                    href="{{ route('dues.coupons', ['year' => $year, 'month' => $month]) }}"
                    target="_blank"
                    icon="heroicon-o-printer"
                    color="gray">
                    Cetak Kupon
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{-- Kartu statistik --}}
    @livewire(\App\Filament\Widgets\DuesReportStats::class, ['year' => $year, 'month' => $month], key('dues-stats-'.$year.'-'.$month))

    {{-- Rekap per kelompok --}}
    @livewire(\App\Filament\Widgets\DuesGroupSummary::class, ['year' => $year, 'month' => $month], key('dues-group-'.$year.'-'.$month))

    {{-- Daftar tunggakan --}}
    @livewire(\App\Filament\Widgets\DuesArrearsList::class, ['year' => $year, 'month' => $month], key('dues-arrears-'.$year.'-'.$month))
</x-filament-panels::page>
