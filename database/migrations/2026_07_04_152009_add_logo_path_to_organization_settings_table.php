<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            // Path (relatif disk `public`) ke logo yang diunggah admin; dicetak
            // pada kupon iuran, kartu anggota, dan daftar anggota. Kosong = pakai
            // logo bawaan public/images/logo-guangzao.png.
            $table->string('logo_path')->nullable()->after('name_hanzi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
