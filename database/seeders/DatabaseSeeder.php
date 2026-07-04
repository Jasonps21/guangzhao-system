<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // §2 — wajib minimal 2 Super Admin sejak awal (tidak boleh satu kunci tunggal).
        User::query()->create([
            'name' => 'Super Admin 1',
            'email' => 'superadmin1@guangzhao.test',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Super Admin 2',
            'email' => 'superadmin2@guangzhao.test',
            'password' => Hash::make('password'),
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        $bendahara = User::query()->create([
            'name' => 'Bendahara',
            'email' => 'admin@guangzhao.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $kolektor = User::query()->create([
            'name' => 'Kolektor Daya',
            'email' => 'kolektor@guangzhao.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Kolektor,
            'is_active' => true,
        ]);

        // Demo data — kelompok, anggota, dan penugasan kolektor.
        $groups = MemberGroup::factory(3)->create();
        $kolektor->groups()->attach($groups->first());

        $groups->each(function (MemberGroup $group): void {
            Member::factory(15)->inGroup($group)->create();
        });

        unset($bendahara);
    }
}
