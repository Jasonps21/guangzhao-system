<?php

namespace App\Models;

use App\Enums\RelationshipType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Satu arah hubungan kekeluargaan antar-anggota (member -> related_member).
 * Setiap baris otomatis disertai baris kebalikannya agar relasi selalu bisa
 * ditelusuri dari kedua sisi dan tidak pernah timpang.
 */
class MemberRelationship extends Model
{
    use LogsActivity;

    protected $fillable = [
        'member_id',
        'related_member_id',
        'relationship_type',
    ];

    protected function casts(): array
    {
        return [
            'relationship_type' => RelationshipType::class,
        ];
    }

    /**
     * Penjaga agar pembuatan/penghapusan sisi kebalikan tidak memicu rekursi.
     */
    protected static bool $syncingReciprocal = false;

    protected static function booted(): void
    {
        static::created(function (MemberRelationship $relationship): void {
            if (static::$syncingReciprocal) {
                return;
            }

            static::$syncingReciprocal = true;

            try {
                // Sisi kebalikan dibuat sistem, bukan pengguna. Tanpa ini satu
                // tindakan tampak dua kali di log audit.
                activity()->withoutLogs(fn () => static::query()->firstOrCreate([
                    'member_id' => $relationship->related_member_id,
                    'related_member_id' => $relationship->member_id,
                ], [
                    'relationship_type' => $relationship->relationship_type->inverse(),
                ]));
            } finally {
                static::$syncingReciprocal = false;
            }
        });

        static::updated(function (MemberRelationship $relationship): void {
            if (static::$syncingReciprocal || ! $relationship->wasChanged('relationship_type')) {
                return;
            }

            static::$syncingReciprocal = true;

            try {
                static::query()
                    ->where('member_id', $relationship->related_member_id)
                    ->where('related_member_id', $relationship->member_id)
                    ->update(['relationship_type' => $relationship->relationship_type->inverse()]);
            } finally {
                static::$syncingReciprocal = false;
            }
        });

        static::deleted(function (MemberRelationship $relationship): void {
            if (static::$syncingReciprocal) {
                return;
            }

            static::$syncingReciprocal = true;

            try {
                static::query()
                    ->where('member_id', $relationship->related_member_id)
                    ->where('related_member_id', $relationship->member_id)
                    ->delete();
            } finally {
                static::$syncingReciprocal = false;
            }
        });
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function relatedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }

    /**
     * Label relasi yang memperhitungkan gender anggota terkait (mis. "Istri").
     */
    public function label(): string
    {
        return $this->relationship_type->genderedLabel($this->relatedMember?->gender);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['member_id', 'related_member_id', 'relationship_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
