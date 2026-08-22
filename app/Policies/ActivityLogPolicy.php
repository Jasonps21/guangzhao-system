<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityRecorder;

/**
 * Jejak audit hanya boleh dibaca, dan hanya oleh Super Admin.
 *
 * Tidak ada satu pun izin menulis — bukan kelalaian, melainkan inti gunanya:
 * jejak yang bisa ditambah, diubah, atau dihapus tidak membuktikan apa-apa.
 * Barisnya lahir dari trait LogsActivity pada model dan dari
 * {@see ActivityRecorder}, tidak pernah dari tangan pengguna.
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
