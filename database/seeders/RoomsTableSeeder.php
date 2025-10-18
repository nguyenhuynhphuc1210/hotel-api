<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomsTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Standard', 'Deluxe', 'Suite'];
        $statuses = ['available', 'booked', 'cleaning'];

        for ($i = 1; $i <= 10; $i++) {
            DB::table('rooms')->insert([
                'room_number' => "R$i",
                'type' => $types[array_rand($types)],
                'price' => rand(500000, 2000000),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
