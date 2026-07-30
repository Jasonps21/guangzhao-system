<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah nilai "migrasi" agar iuran hasil impor saldo awal dari rekap Excel
     * bisa dibedakan dari uang yang benar-benar ditagih lewat aplikasi.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE dues_records MODIFY collection_method ENUM('lapangan', 'admin', 'migrasi') NULL");
    }

    public function down(): void
    {
        DB::table('dues_records')->where('collection_method', 'migrasi')->update(['collection_method' => null]);

        DB::statement("ALTER TABLE dues_records MODIFY collection_method ENUM('lapangan', 'admin') NULL");
    }
};
