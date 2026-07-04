<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel baris-tunggal berisi identitas organisasi yang dicetak pada kupon iuran. (§F)
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('PERKUMPULAN SOSIAL GUANG ZHAO');
            $table->string('name_hanzi')->default('印尼锡江廣肇友好同乡会');
            $table->string('contact_line')->nullable();
            $table->string('chairman_name')->nullable();
            $table->string('treasurer_name')->nullable();
            $table->timestamps();
        });

        DB::table('organization_settings')->insert([
            'name' => 'PERKUMPULAN SOSIAL GUANG ZHAO',
            'name_hanzi' => '印尼锡江廣肇友好同乡会',
            'contact_line' => 'JL. BONTOSUA 1N TLP: 3617538 / HP: 085100065372 MKS',
            'chairman_name' => 'Jonrik',
            'treasurer_name' => 'Jefry Kenang',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
