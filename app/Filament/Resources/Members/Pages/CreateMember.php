<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Support\DuesGenerator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * Anggota baru langsung ditagih untuk bulan berjalan. Tanpa ini ia tak
     * muncul di layar kolektor/pencatat lokasi sampai dues:generate berikutnya.
     */
    protected function afterCreate(): void
    {
        $now = Carbon::now();

        app(DuesGenerator::class)->generateForMember($this->record, (int) $now->year, (int) $now->month);
    }
}
