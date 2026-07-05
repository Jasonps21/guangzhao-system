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
use Illuminate\Support\Facades\Storage;
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
        'latitude',
        'longitude',
        'house_photo_path',
        'location_updated_at',
        'location_updated_by',
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
            'location_updated_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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
            ->logOnly(['member_number', 'name_hanzi', 'name_pinyin', 'name_indonesian', 'status', 'monthly_dues_amount', 'group_id', 'address', 'latitude', 'longitude'])
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
     * Riwayat kunjungan lapangan (terbaru dulu).
     *
     * @return HasMany<MemberVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(MemberVisit::class)->latest('visited_at');
    }

    /**
     * Petugas yang terakhir memperbarui titik lokasi.
     *
     * @return BelongsTo<User, $this>
     */
    public function locationUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'location_updated_by');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Lokasi dianggap lengkap bila sudah ada titik GPS dan foto rumah — syarat
     * kebijakan "wajib sekali" saat kunjungan pertama.
     */
    public function hasCompleteLocation(): bool
    {
        return $this->hasLocation() && filled($this->house_photo_path);
    }

    /**
     * Tautan Google Maps untuk melihat titik rumah. Null bila belum ada titik.
     */
    public function googleMapsUrl(): ?string
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
    }

    /**
     * Tautan navigasi (rute) Google Maps menuju rumah anggota.
     */
    public function googleMapsDirectionsUrl(): ?string
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.$this->latitude.','.$this->longitude;
    }

    /**
     * URL foto rumah terkini pada disk publik. Null bila belum ada.
     */
    public function housePhotoUrl(): ?string
    {
        return filled($this->house_photo_path)
            ? Storage::disk('public')->url($this->house_photo_path)
            : null;
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
