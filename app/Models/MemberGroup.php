<?php

namespace App\Models;

use App\Enums\GroupBasis;
use Database\Factories\MemberGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberGroup extends Model
{
    /** @use HasFactory<MemberGroupFactory> */
    use HasFactory;

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
}
