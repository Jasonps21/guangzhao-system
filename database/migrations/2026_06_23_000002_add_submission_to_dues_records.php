<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Tambah status "menunggu_persetujuan" dan tautan ke setoran kolektor. (§6.4)
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE dues_records MODIFY status ENUM('belum_bayar', 'menunggu_persetujuan', 'lunas') NOT NULL DEFAULT 'belum_bayar'");

        Schema::table('dues_records', function (Blueprint $table) {
            $table->foreignId('submission_id')->nullable()->after('collection_method')
                ->constrained('payment_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dues_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_id');
        });

        DB::statement("ALTER TABLE dues_records MODIFY status ENUM('belum_bayar', 'lunas') NOT NULL DEFAULT 'belum_bayar'");
    }
};
