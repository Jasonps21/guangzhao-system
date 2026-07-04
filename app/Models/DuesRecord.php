<?php

namespace App\Models;

use App\Enums\CollectionMethod;
use App\Enums\DuesStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DuesRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DuesRecord extends Model
{
    /** @use HasFactory<DuesRecordFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'member_id',
        'period_year',
        'period_month',
        'amount_due',
        'amount_paid',
        'status',
        'paid_at',
        'recorded_by',
        'collection_method',
        'submission_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DuesStatus::class,
            'collection_method' => CollectionMethod::class,
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount_paid', 'paid_at', 'recorded_by', 'collection_method'])
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
     * @return BelongsTo<PaymentSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class, 'submission_id');
    }

    /**
     * Mark this record paid in full. (§6.2 / §6.3)
     */
    public function markPaid(User $by, CollectionMethod $method, ?CarbonImmutable $paidAt = null): bool
    {
        if ($this->status === DuesStatus::Lunas) {
            return false;
        }

        $this->forceFill([
            'status' => DuesStatus::Lunas,
            'amount_paid' => $this->amount_due,
            'paid_at' => $paidAt ?? CarbonImmutable::now(),
            'recorded_by' => $by->getKey(),
            'collection_method' => $method,
        ])->save();

        return true;
    }

    /**
     * Catat pembayaran multi-bulan kolektor sebagai MENUNGGU PERSETUJUAN admin. (§6.4)
     * Hanya berlaku untuk tagihan yang belum diproses sama sekali.
     */
    public function markPendingApproval(User $by, PaymentSubmission $submission): bool
    {
        if ($this->status !== DuesStatus::BelumBayar) {
            return false;
        }

        $this->forceFill([
            'status' => DuesStatus::MenungguPersetujuan,
            'amount_paid' => $this->amount_due,
            'paid_at' => null,
            'recorded_by' => $by->getKey(),
            'collection_method' => CollectionMethod::Lapangan,
            'submission_id' => $submission->getKey(),
        ])->save();

        return true;
    }

    /**
     * Admin menyetujui setoran: tagihan menunggu menjadi LUNAS,
     * kolektor pencatat tetap dipertahankan. (§6.4)
     */
    public function confirmPaid(?CarbonImmutable $paidAt = null): bool
    {
        if ($this->status !== DuesStatus::MenungguPersetujuan) {
            return false;
        }

        $this->forceFill([
            'status' => DuesStatus::Lunas,
            'paid_at' => $paidAt ?? CarbonImmutable::now(),
        ])->save();

        return true;
    }

    /**
     * Admin menolak setoran: tagihan kembali BELUM BAYAR dan lepas dari setoran. (§6.4)
     */
    public function releaseToUnpaid(): bool
    {
        if ($this->status !== DuesStatus::MenungguPersetujuan) {
            return false;
        }

        $this->forceFill([
            'status' => DuesStatus::BelumBayar,
            'amount_paid' => null,
            'paid_at' => null,
            'recorded_by' => null,
            'collection_method' => null,
            'submission_id' => null,
        ])->save();

        return true;
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->period_year, $this->period_month, 1)
            ->translatedFormat('F Y');
    }

    /**
     * @param  Builder<DuesRecord>  $query
     */
    public function scopeForPeriod(Builder $query, int $year, int $month): void
    {
        $query->where('period_year', $year)->where('period_month', $month);
    }

    /**
     * @param  Builder<DuesRecord>  $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', DuesStatus::BelumBayar);
    }
}
