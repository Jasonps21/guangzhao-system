<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentSubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PaymentSubmission extends Model
{
    /** @use HasFactory<PaymentSubmissionFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'member_id',
        'collector_id',
        'period_year',
        'from_month',
        'to_month',
        'total_amount',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'total_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reviewed_by', 'reviewed_at', 'review_notes'])
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
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<DuesRecord, $this>
     */
    public function duesRecords(): HasMany
    {
        return $this->hasMany(DuesRecord::class, 'submission_id');
    }

    public function periodRangeLabel(): string
    {
        $from = Carbon::create($this->period_year, $this->from_month, 1)->translatedFormat('F Y');

        if ($this->from_month === $this->to_month) {
            return $from;
        }

        return $from.' – '.Carbon::create($this->period_year, $this->to_month, 1)->translatedFormat('F Y');
    }

    public function monthsCount(): int
    {
        return $this->to_month - $this->from_month + 1;
    }

    public function isPending(): bool
    {
        return $this->status === SubmissionStatus::Pending;
    }

    public function approve(User $by): void
    {
        $this->forceFill([
            'status' => SubmissionStatus::Approved,
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => CarbonImmutable::now(),
        ])->save();
    }

    public function reject(User $by, ?string $notes = null): void
    {
        $this->forceFill([
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => CarbonImmutable::now(),
            'review_notes' => $notes,
        ])->save();
    }

    /**
     * @param  Builder<PaymentSubmission>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Pending);
    }
}
