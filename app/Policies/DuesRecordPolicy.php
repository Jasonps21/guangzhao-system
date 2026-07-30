<?php

namespace App\Policies;

/**
 * Admin boleh mencatat & mengoreksi iuran, hanya Super Admin yang boleh menghapus.
 */
class DuesRecordPolicy extends ManagedRecordPolicy {}
