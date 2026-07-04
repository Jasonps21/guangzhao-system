<?php

namespace App\Exports;

use App\Models\DuesRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MonthlyDuesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        public int $year,
        public int $month,
    ) {}

    /**
     * @return Collection<int, DuesRecord>
     */
    public function collection(): Collection
    {
        return DuesRecord::query()
            ->with('member.group')
            ->forPeriod($this->year, $this->month)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['No. Anggota', 'Nama', 'Kelompok', 'Periode', 'Tagihan', 'Dibayar', 'Status', 'Tgl Bayar', 'Metode'];
    }

    /**
     * @param  DuesRecord  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        return [
            $record->member?->member_number,
            $record->member?->displayName(),
            $record->member?->group?->name,
            Carbon::create($record->period_year, $record->period_month, 1)->translatedFormat('F Y'),
            (float) $record->amount_due,
            (float) $record->amount_paid,
            $record->status->getLabel(),
            $record->paid_at?->format('Y-m-d'),
            $record->collection_method?->getLabel(),
        ];
    }
}
