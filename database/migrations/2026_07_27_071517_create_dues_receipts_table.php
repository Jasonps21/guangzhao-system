<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota pembayaran iuran beberapa bulan sekaligus (pembayaran langsung di
     * kantor). Disimpan sebagai baris tersendiri supaya nomornya tetap dan
     * notanya bisa dicetak ulang persis sama bila kertasnya rusak.
     */
    public function up(): void
    {
        Schema::create('dues_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->smallInteger('period_year');
            $table->tinyInteger('from_month');
            $table->tinyInteger('to_month');
            $table->unsignedSmallInteger('months_count');
            $table->decimal('total_amount', 12, 2);
            $table->date('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('issued_at');
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dues_receipts');
    }
};
