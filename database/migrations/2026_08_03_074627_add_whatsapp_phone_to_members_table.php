<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan "nomor anggota" dari "nomor yang bisa dihubungi lewat WhatsApp".
 * Keduanya sering berbeda: anggota lansia memakai telepon rumah, sementara
 * yang memegang WhatsApp adalah anak atau menantunya. Sebelumnya keduanya
 * dipaksa masuk kolom `phone` dipisah garis miring, sehingga nomornya tidak
 * bisa dipakai mengirim pesan tanpa ditebak lebih dulu.
 *
 * Hanya menambah kolom — tidak ada data lama yang diubah atau dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('whatsapp_phone')->nullable()->after('phone');
            $table->string('whatsapp_owner')->nullable()->after('whatsapp_phone');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_phone', 'whatsapp_owner']);
        });
    }
};
