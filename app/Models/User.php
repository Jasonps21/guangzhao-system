<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Groups handled by this collector. (collector_group pivot)
     *
     * @return BelongsToMany<MemberGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'collector_group', 'collector_id', 'group_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<DuesRecord, $this>
     */
    public function recordedDuesRecords(): HasMany
    {
        return $this->hasMany(DuesRecord::class, 'recorded_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCollector(): bool
    {
        return $this->role === UserRole::Kolektor;
    }

    /**
     * Super admin + admin have full administrative reach.
     */
    public function isManager(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isManager();
    }
}
