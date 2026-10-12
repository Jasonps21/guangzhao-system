<?php

namespace App\Models;

use App\Enums\GroupBasis;
use Database\Factories\MemberGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MemberGroup extends Model
{
    /** @use HasFactory<MemberGroupFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'basis',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'basis' => GroupBasis::class,
        ];
    }

    /**
     * Awalan nomor anggota: angka pertama di nama kelompok ("Kelompok 10
     * (Bebas Iuran)" → "10"). Kelompok yang namanya tanpa angka memakai id-nya,
     * agar nomor anggotanya tetap terbentuk dan tidak bentrok dengan kelompok lain.
     */
    public function memberNumberPrefix(): string
    {
        if (preg_match('/\d+/', (string) $this->name, $match) === 1) {
            return (string) (int) $match[0];
        }

        return (string) $this->getKey();
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'group_id');
    }

    /**
     * Collectors assigned to this group.
     *
     * @return BelongsToMany<User, $this>
     */
    public function collectors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'collector_group', 'group_id', 'collector_id')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'basis', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
