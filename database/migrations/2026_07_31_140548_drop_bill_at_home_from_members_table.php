<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "tagih di rumah" dihapus. Satu-satunya efeknya adalah mencetak
 * keterangan "(tagih dirumah)" di kupon iuran — tidak memengaruhi penagihan,
 * laporan, maupun aplikasi lapangan — dan di praktiknya tidak dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('bill_at_home');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('bill_at_home')->default(false)->after('no_urut');
        });
    }
};
