<?php

namespace App\Models;

use App\Enums\SocialFundDirection;
use App\Enums\SocialFundForm;
use App\Models\Concerns\HasReceiptNumber;
use App\Support\Terbilang;
use Database\Factories\SocialFundRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Dana sosial: sumbangan yang diterima dari donatur (MASUK) maupun bantuan
 * sosial yang diberikan perkumpulan (KELUAR), dalam bentuk uang atau barang.
 */
class SocialFundRecord extends Model
{
    /** @use HasFactory<SocialFundRecordFactory> */
    use HasFactory, HasReceiptNumber, LogsActivity;

    protected $fillable = [
        'receipt_number',
        'direction',
        'fund_form',
        'party_name',
        'party_address',
        'amount',
        'item_description',
        'purpose',
        'transacted_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => SocialFundDirection::class,
            'fund_form' => SocialFundForm::class,
            'amount' => 'decimal:2',
            'transacted_at' => 'date',
        ];
    }

    public static function receiptPrefix(): string
    {
        return 'BTN';
    }

    protected static function receiptDateColumn(): ?string
    {
        return 'transacted_at';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_number', 'direction', 'fund_form', 'party_name', 'amount', 'transacted_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isIncoming(): bool
    {
        return $this->direction === SocialFundDirection::Masuk;
    }

    public function isInGoods(): bool
    {
        return $this->fund_form === SocialFundForm::Barang;
    }

    /**
     * Ejaan nominal untuk nota. Kosong bila sumbangan barang tanpa taksiran.
     */
    public function amountInWords(): ?string
    {
        return $this->amount === null ? null : Terbilang::rupiah((float) $this->amount);
    }

    /**
     * @param  Builder<SocialFundRecord>  $query
     */
    public function scopeIncoming(Builder $query): void
    {
        $query->where('direction', SocialFundDirection::Masuk);
    }

    /**
     * @param  Builder<SocialFundRecord>  $query
     */
    public function scopeOutgoing(Builder $query): void
    {
        $query->where('direction', SocialFundDirection::Keluar);
    }
}
