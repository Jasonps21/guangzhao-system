<?php

namespace App\Policies;

use App\Models\User;

/**
 * Baseline aturan akses untuk data yang dikelola di panel admin.
 *
 * Super Admin dan Admin sama-sama boleh membaca, menambah, dan mengubah data.
 * Aksi yang merusak data (hapus, hapus permanen, pulihkan) hanya untuk Super Admin,
 * sehingga tombolnya otomatis hilang dari UI Filament sekaligus ditolak di server.
 *
 * Subclass hanya perlu menimpa method yang aturannya berbeda.
 */
abstract class ManagedRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user): bool
    {
        return $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user): bool
    {
        return $user->isManager();
    }

    public function delete(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
