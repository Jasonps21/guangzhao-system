<?php

namespace App\Policies;

/**
 * Admin boleh mencatat & mengubah uang duka, hanya Super Admin yang boleh menghapus.
 */
class CondolencePaymentPolicy extends ManagedRecordPolicy {}
