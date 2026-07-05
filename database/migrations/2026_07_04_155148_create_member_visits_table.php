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
        // Jejak kunjungan lapangan — riwayat permanen agar pengetahuan lokasi
        // tidak hilang saat penagih keluar/meninggal. (§ data lapangan)
        Schema::create('member_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visited_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('address_snapshot')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('outcome')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'visited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_visits');
    }
};
