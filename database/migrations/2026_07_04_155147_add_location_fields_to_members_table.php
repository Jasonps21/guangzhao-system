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
            // Titik rumah terkini (diambil GPS HP kolektor di lapangan) & foto rumah
            // terkini. Riwayat perubahan tersimpan di tabel member_visits.
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('house_photo_path')->nullable()->after('longitude');
            $table->timestamp('location_updated_at')->nullable()->after('house_photo_path');
            $table->foreignId('location_updated_by')->nullable()->after('location_updated_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_updated_by');
            $table->dropColumn(['latitude', 'longitude', 'house_photo_path', 'location_updated_at']);
        });
    }
};
