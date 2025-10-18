<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomersTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            DB::table('customers')->insert([
                'fullname' => "Customer $i",
                'phone' => "09111111$i",
                'email' => "customer$i@example.com",
                'cccd' => "12345678$i",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
