<?php

namespace App\Models;

use App\Enums\DuesCategory;
use App\Enums\MemberStatus;
use App\Support\PinyinConverter;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'member_number',
        'name_hanzi',
        'name_pinyin',
        'name_indonesian',
        'address',
        'phone',
        'photo_path',
        'dues_category',
        'monthly_dues_amount',
        'group_id',
        'no_urut',
        'bill_at_home',
        'status',
        'status_changed_at',
        'joined_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dues_category' => DuesCategory::class,
            'status' => MemberStatus::class,
            'bill_at_home' => 'boolean',
            'monthly_dues_amount' => 'decimal:2',
            'status_changed_at' => 'datetime',
            'joined_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            // §6.6 — auto-fill pinyin from hanzi when pinyin is empty (still overridable).
            if (filled($member->name_hanzi) && blank($member->name_pinyin)) {
                $member->name_pinyin = app(PinyinConverter::class)->fromName($member->name_hanzi);
            }

            // §E — record the moment status changes so it is auditable.
            if ($member->isDirty('status')) {
                $member->status_changed_at = now();
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['member_number', 'name_hanzi', 'name_pinyin', 'name_indonesian', 'status', 'monthly_dues_amount', 'group_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<MemberGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MemberGroup::class, 'group_id');
    }

    /**
     * @return HasMany<DuesRecord, $this>
     */
    public function duesRecords(): HasMany
    {
        return $this->hasMany(DuesRecord::class);
    }

    /**
     * Only members that should be billed, printed coupons/cards for. (§6.5)
     *
     * @param  Builder<Member>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', MemberStatus::Aktif);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Aktif;
    }

    public function displayName(): string
    {
        return $this->name_indonesian
            ?: $this->name_pinyin
            ?: $this->name_hanzi
            ?: $this->member_number;
    }
}
