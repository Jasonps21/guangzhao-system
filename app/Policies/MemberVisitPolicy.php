<?php

namespace App\Policies;

use App\Models\User;

/**
 * Riwayat kunjungan dicatat otomatis oleh kolektor di lapangan.
 * Di panel admin sifatnya hanya baca supaya jejak kunjungan tidak bisa diubah.
 */
class MemberVisitPolicy extends ManagedRecordPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }
}
