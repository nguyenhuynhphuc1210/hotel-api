<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoicesTableSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['unpaid', 'paid', 'cancelled'];

        for ($i = 1; $i <= 10; $i++) {
            DB::table('invoices')->insert([
                'booking_id' => $i,
                'total_amount' => rand(1000000, 5000000),
                'payment_date' => now()->addDays(rand(-5, 5)),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
