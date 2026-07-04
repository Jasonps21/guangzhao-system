<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'name',
        'name_hanzi',
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
}
