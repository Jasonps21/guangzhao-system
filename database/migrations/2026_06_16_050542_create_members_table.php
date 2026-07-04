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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number')->unique();
            $table->string('name_hanzi')->nullable();
            $table->string('name_pinyin')->nullable();
            $table->string('name_indonesian')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->enum('dues_category', ['prasejahtera', 'kurang_mampu', 'menengah', 'mampu'])->nullable();
            $table->decimal('monthly_dues_amount', 12, 2)->default(0);
            $table->foreignId('group_id')->nullable()->constrained('member_groups')->nullOnDelete();
            $table->enum('status', ['aktif', 'mengundurkan_diri', 'pindah', 'meninggal'])->default('aktif');
            $table->timestamp('status_changed_at')->nullable();
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
