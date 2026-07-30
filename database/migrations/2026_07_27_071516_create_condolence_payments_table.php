<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemberian uang duka kepada keluarga anggota. Satu baris menghasilkan dua
     * nota cetak: TANDA TERIMA (ditandatangani keluarga penerima) dan TANDA
     * PEMBERIAN (ditandatangani pengurus / 福利部).
     */
    public function up(): void
    {
        Schema::create('condolence_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            // Anggota terkait bila ada; nama & nomornya tetap disalin agar bunyi
            // nota lama tidak ikut berubah saat data anggota diperbarui.
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_member_number')->nullable();
            $table->string('deceased_name_hanzi')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('escort_name')->nullable();
            $table->date('given_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('given_at');
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condolence_payments');
    }
};
