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
        Schema::table('members', function (Blueprint $table): void {
            // Menentukan label kekeluargaan (suami/istri, ayah/ibu, dst.). Boleh
            // kosong untuk anggota lama — label akan jatuh ke bentuk netral.
            $table->enum('gender', ['male', 'female'])->nullable()->after('name_indonesian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn('gender');
        });
    }
};
