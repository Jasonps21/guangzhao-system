<?php

namespace App\Models;

use App\Enums\VisitOutcome;
use Database\Factories\MemberVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kunjungan lapangan ke rumah anggota. Riwayat permanen supaya penerus
 * penagih tetap tahu titik & foto rumah walau penagih lama sudah keluar.
 */
class MemberVisit extends Model
{
    /** @use HasFactory<MemberVisitFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'user_id',
        'visited_at',
        'latitude',
        'longitude',
        'address_snapshot',
        'photo_path',
        'outcome',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'outcome' => VisitOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Kolektor/petugas yang melakukan kunjungan.
     *
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
