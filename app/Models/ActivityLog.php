<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Satu baris jejak audit.
 *
 * Membungkus model bawaan spatie/laravel-activitylog supaya golongan kegiatan
 * bisa dibaca sebagai enum dan penyaringnya punya tempat tinggal. Tabelnya
 * sama persis — tidak ada migrasi baru dan riwayat lama tetap terbaca.
 */
class ActivityLog extends Activity
{
    public function category(): ActivityCategory
    {
        return ActivityCategory::tryFrom((string) $this->log_name)
            ?? ActivityCategory::PerubahanData;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfCategory(Builder $query, ActivityCategory $category): void
    {
        $query->where('log_name', $category->value);
    }
}
