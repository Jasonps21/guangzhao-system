<?php

namespace App\Models;

use App\Models\Concerns\HasReceiptNumber;
use App\Support\Terbilang;
use Database\Factories\DuesReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Nota pembayaran iuran beberapa bulan sekaligus yang dibayar langsung di
 * kantor. Disimpan agar nomornya tetap dan notanya bisa dicetak ulang. (§6.4)
 */
class DuesReceipt extends Model
{
    /** @use HasFactory<DuesReceiptFactory> */
    use HasFactory, HasReceiptNumber, LogsActivity;

    protected $fillable = [
        'receipt_number',
        'member_id',
        'period_year',
        'from_month',
        'to_month',
        'months_count',
        'total_amount',
        'issued_at',
        'issued_by',
        'received_by_name',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'issued_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_number', 'member_id', 'period_year', 'from_month', 'to_month', 'months_count', 'total_amount', 'issued_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function receiptPrefix(): string
    {
        return 'IUR';
    }

    protected static function receiptDateColumn(): ?string
    {
        return 'issued_at';
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Tagihan bulanan yang dilunasi oleh nota ini.
     *
     * @return HasMany<DuesRecord, $this>
     */
    public function duesRecords(): HasMany
    {
        return $this->hasMany(DuesRecord::class, 'receipt_id');
    }

    public function fromMonthLabel(): string
    {
        return Carbon::create($this->period_year, $this->from_month, 1)->translatedFormat('F');
    }

    public function toMonthLabel(): string
    {
        return Carbon::create($this->period_year, $this->to_month, 1)->translatedFormat('F');
    }

    public function periodRangeLabel(): string
    {
        return $this->from_month === $this->to_month
            ? $this->fromMonthLabel().' '.$this->period_year
            : $this->fromMonthLabel().' s/d '.$this->toMonthLabel().' '.$this->period_year;
    }

    public function totalInWords(): string
    {
        return Terbilang::rupiah((float) $this->total_amount);
    }

    /**
     * Nama yang tercetak pada baris "YANG MENERIMA". Memakai nama yang diketik
     * petugas bila uangnya dititipkan lewat orang lain; jika kosong, jatuh ke
     * nama petugas yang menerbitkan nota.
     */
    public function receiverName(): ?string
    {
        return $this->received_by_name ?: $this->issuedBy?->name;
    }
}
