<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pengelolaan pengguna sepenuhnya milik Super Admin. Admin tidak boleh melihat,
 * membuat, atau mengubah akun apa pun — termasuk menaikkan peran dirinya sendiri.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Super admin tidak boleh menghapus akunnya sendiri agar panel tidak terkunci.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && ! $user->is($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperAdmin();
    }
}
