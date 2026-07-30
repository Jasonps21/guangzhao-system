<?php

namespace App\Policies;

/**
 * Admin boleh mencatat & mengubah dana sosial, hanya Super Admin yang boleh menghapus.
 */
class SocialFundRecordPolicy extends ManagedRecordPolicy {}
