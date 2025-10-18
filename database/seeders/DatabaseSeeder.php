<?php

namespace Database\Seeders;

use App\Models\RoomImage;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UsersTableSeeder::class,
            CustomersTableSeeder::class,
            RoomsTableSeeder::class,
            BookingsTableSeeder::class,
            ServicesTableSeeder::class,
            BookingServicesTableSeeder::class,
            InvoicesTableSeeder::class,
            RoomImagesTableSeeder::class,
        ]);
    }
}
