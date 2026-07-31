<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Nama pengguna pendek untuk login. Kolektor di lapangan sebagian besar lansia,
 * dan mengetik alamat email panjang di keyboard HP adalah sumber gagal masuk
 * yang paling sering. Username diisi otomatis dari bagian depan email supaya
 * akun lama langsung bisa dipakai tanpa admin menyentuh apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('name');
        });

        $this->backfillUsernames();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }

    /**
     * Turunkan username dari bagian lokal email, lalu bereskan bentrokan
     * dengan akhiran angka (budi, budi2, budi3, …).
     */
    private function backfillUsernames(): void
    {
        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::of((string) $user->email)
                ->before('@')
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '')
                ->value();

            if ($base === '') {
                $base = 'user'.$user->id;
            }

            $username = $base;
            $suffix = 2;

            while (in_array($username, $taken, true)) {
                $username = $base.$suffix;
                $suffix++;
            }

            $taken[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }
    }
};
