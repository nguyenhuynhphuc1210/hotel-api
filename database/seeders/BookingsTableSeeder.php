<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingsTableSeeder extends Seeder
{
    public function run(): void
    {
        // Cập nhật status để có thêm deposit_paid
        $statuses = ['pending', 'confirmed', 'cancelled', 'completed'];

        for ($i = 1; $i <= 10; $i++) {
            $totalPrice = rand(500000, 5000000);
            $deposit = round($totalPrice * 0.2, -3); // làm tròn đến nghìn
            $remaining = $totalPrice - $deposit;

            DB::table('bookings')->insert([
                'room_id'         => rand(1, 10),
                'customer_id'     => rand(1, 9),
                'checkin_date'    => now()->addDays(rand(0, 5)),
                'checkout_date'   => now()->addDays(rand(6, 10)),
                'status'          => $statuses[array_rand($statuses)],
                'total_price'     => $totalPrice,
                'deposit_amount'  => $deposit,
                'remaining_amount'=> $remaining,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}
