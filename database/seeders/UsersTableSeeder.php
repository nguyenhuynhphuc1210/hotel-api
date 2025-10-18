<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [0, 1, 2]; // 0: admin, 1: staff, 2: customer

        for ($i = 1; $i <= 9; $i++) {
            DB::table('users')->insert([
                'fullname' => "User $i",
                'email' => "user$i@example.com",
                'password' => Hash::make('123456'),
                'role' => $roles[array_rand($roles)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
