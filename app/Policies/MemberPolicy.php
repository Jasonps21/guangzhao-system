<?php

namespace App\Policies;

/**
 * Admin boleh menambah & mengubah anggota, hanya Super Admin yang boleh menghapus.
 */
class MemberPolicy extends ManagedRecordPolicy {}
