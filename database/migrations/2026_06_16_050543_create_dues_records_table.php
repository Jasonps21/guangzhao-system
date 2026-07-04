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
        Schema::create('dues_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->enum('status', ['belum_bayar', 'lunas'])->default('belum_bayar');
            $table->date('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('collection_method', ['lapangan', 'admin'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'period_year', 'period_month']);
            $table->index(['period_year', 'period_month']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dues_records');
    }
};
