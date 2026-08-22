<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /**
     * Tanpa tombol apa pun di kepala halaman — tidak ada yang bisa ditambahkan
     * ke jejak audit dari sini.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
