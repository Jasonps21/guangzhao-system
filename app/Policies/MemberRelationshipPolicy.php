<?php

namespace App\Policies;

/**
 * Relasi kekeluargaan dikelola lewat relation manager pada halaman anggota.
 * Admin boleh menambah & mengubah, hanya Super Admin yang boleh menghapus.
 */
class MemberRelationshipPolicy extends ManagedRecordPolicy {}
