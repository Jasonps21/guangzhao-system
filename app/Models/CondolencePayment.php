<?php

namespace App\Models;

use App\Models\Concerns\HasReceiptNumber;
use App\Support\ChineseNumeral;
use App\Support\Terbilang;
use Database\Factories\CondolencePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Pemberian uang duka kepada keluarga anggota yang meninggal. Satu baris
 * dicetak menjadi dua nota: TANDA TERIMA (keluarga) dan TANDA PEMBERIAN
 * (pengurus / 福利部).
 */
class CondolencePayment extends Model
{
    /** @use HasFactory<CondolencePaymentFactory> */
    use HasFactory, HasReceiptNumber, LogsActivity;

    protected $fillable = [
        'receipt_number',
        'member_id',
        'recipient_name',
        'recipient_member_number',
        'deceased_name_hanzi',
        'amount',
        'escort_name',
        'given_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'given_at' => 'date',
        ];
    }

    public static function receiptPrefix(): string
    {
        return 'DUK';
    }

    protected static function receiptDateColumn(): ?string
    {
        return 'given_at';
    }

    protected static function booted(): void
    {
        static::saving(function (CondolencePayment $payment): void {
            // Nama & nomor anggota disalin sekali saat kosong supaya nota yang
            // sudah dicetak tetap cocok dengan arsip kertasnya.
            $member = $payment->member_id !== null ? $payment->member : null;

            if ($member === null) {
                return;
            }

            $payment->recipient_name = $payment->recipient_name ?: $member->displayName();
            $payment->recipient_member_number = $payment->recipient_member_number ?: $member->member_number;
            $payment->deceased_name_hanzi = $payment->deceased_name_hanzi ?: $member->name_hanzi;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_number', 'member_id', 'recipient_name', 'amount', 'given_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Ejaan nominal untuk nota, mis. "satu juta rupiah".
     */
    public function amountInWords(): string
    {
        return Terbilang::rupiah((float) $this->amount);
    }

    /**
     * Nominal dalam angka kapital Mandarin, mis. "壹佰万盾正".
     */
    public function amountInChinese(): string
    {
        return ChineseNumeral::rupiah((float) $this->amount);
    }
}
