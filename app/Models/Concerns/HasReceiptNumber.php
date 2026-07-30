<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nomor nota otomatis per jenis & tahun, mis. DUK-2026-0001. Dipakai model nota
 * cetak (uang duka, dana sosial, iuran) supaya tiap lembar punya identitas tetap
 * yang bisa dirujuk saat pencocokan pembukuan.
 */
trait HasReceiptNumber
{
    public static function bootHasReceiptNumber(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->receipt_number)) {
                $model->receipt_number = static::nextReceiptNumber(static::receiptYearFor($model));
            }
        });
    }

    /**
     * Awalan nomor nota, mis. "DUK". Wajib didefinisikan tiap model pemakai.
     */
    abstract public static function receiptPrefix(): string;

    /**
     * Nomor berikutnya pada deret tahun tersebut. Dikunci di dalam transaksi
     * agar dua petugas yang menyimpan bersamaan tidak mendapat nomor kembar.
     */
    public static function nextReceiptNumber(?int $year = null): string
    {
        $year ??= (int) now()->year;
        $prefix = static::receiptPrefix().'-'.$year.'-';

        return DB::transaction(function () use ($prefix): string {
            $last = static::query()
                ->where('receipt_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('receipt_number')
                ->value('receipt_number');

            $sequence = $last === null ? 1 : ((int) Str::afterLast($last, '-')) + 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Tahun deret nomor mengikuti tanggal transaksi nota (bukan tanggal input),
     * supaya nota mundur tetap masuk deret tahun yang benar.
     */
    protected static function receiptYearFor(Model $model): int
    {
        $column = static::receiptDateColumn();
        $date = $column === null ? null : $model->getAttribute($column);

        return $date === null ? (int) now()->year : (int) $date->year;
    }

    /**
     * Kolom tanggal yang menentukan tahun deret nomor. Null → pakai tahun ini.
     */
    protected static function receiptDateColumn(): ?string
    {
        return null;
    }
}
