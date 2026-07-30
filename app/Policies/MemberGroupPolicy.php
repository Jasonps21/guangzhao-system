<?php

namespace App\Policies;

/**
 * Admin boleh menambah & mengubah kelompok, hanya Super Admin yang boleh menghapus.
 */
class MemberGroupPolicy extends ManagedRecordPolicy {}
