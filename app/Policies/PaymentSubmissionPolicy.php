<?php

namespace App\Policies;

use App\Models\User;

/**
 * Setoran dibuat oleh kolektor lewat aplikasi lapangan, bukan dari panel admin.
 * Di panel, manajer hanya meninjau (menyetujui/menolak) dan tidak bisa membuat baru.
 */
class PaymentSubmissionPolicy extends ManagedRecordPolicy
{
    public function create(User $user): bool
    {
        return false;
    }
}
