<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dana sosial dua arah: sumbangan yang MASUK dari donatur dan bantuan yang
     * KELUAR diberikan perkumpulan. Keduanya dicetak sebagai nota TANDA TERIMA
     * dengan bunyi kalimat yang menyesuaikan arahnya.
     */
    public function up(): void
    {
        Schema::create('social_fund_records', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->enum('direction', ['masuk', 'keluar']);
            $table->enum('fund_form', ['uang', 'barang'])->default('uang');
            $table->string('party_name');
            $table->string('party_address')->nullable();
            // Untuk bentuk barang, amount adalah nilai taksiran dan boleh kosong.
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('item_description')->nullable();
            $table->string('purpose')->nullable();
            $table->date('transacted_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transacted_at');
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_fund_records');
    }
};
