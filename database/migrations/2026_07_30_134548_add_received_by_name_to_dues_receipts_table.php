<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama penerima uang dicatat terpisah dari petugas yang menginput: uang
     * iuran bisa dititipkan lewat orang lain, dan nama itulah yang harus
     * tercetak pada baris "YANG MENERIMA" di nota.
     */
    public function up(): void
    {
        Schema::table('dues_receipts', function (Blueprint $table) {
            $table->string('received_by_name')->nullable()->after('issued_by');
        });
    }

    public function down(): void
    {
        Schema::table('dues_receipts', function (Blueprint $table) {
            $table->dropColumn('received_by_name');
        });
    }
};
