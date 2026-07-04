<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            // Nomor urut anggota dalam kelompok untuk mengurutkan kupon iuran saat cetak. (§F)
            $table->unsignedInteger('no_urut')->nullable()->after('group_id');
            // Ditagih di rumah — dicetak sebagai "(tagih dirumah)" pada kupon.
            $table->boolean('bill_at_home')->default(false)->after('no_urut');

            $table->index(['group_id', 'no_urut']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropIndex(['group_id', 'no_urut']);
            $table->dropColumn(['no_urut', 'bill_at_home']);
        });
    }
};
