<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesTableSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['Spa', 300000],
            ['Breakfast', 150000],
            ['Lunch', 200000],
            ['Dinner', 250000],
            ['Laundry', 100000],
            ['Airport Pickup', 500000],
            ['Car Rental', 800000],
            ['Massage', 400000],
            ['Gym', 100000],
            ['Swimming Pool', 150000],
        ];

        foreach ($services as $service) {
            DB::table('services')->insert([
                'service_name' => $service[0],
                'price' => $service[1],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
