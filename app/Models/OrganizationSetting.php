<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrganizationSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'name_hanzi',
        'logo_path',
        'contact_line',
        'chairman_name',
        'treasurer_name',
    ];

    /**
     * The single configuration row, created with sane defaults if missing.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'name' => 'PERKUMPULAN SOSIAL GUANG ZHAO',
            'name_hanzi' => '印尼锡江廣肇友好同乡会',
            'contact_line' => 'JL. BONTOSUA 1N TLP: 3617538 / HP: 085100065372 MKS',
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'name_hanzi', 'contact_line', 'chairman_name', 'treasurer_name', 'logo_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
