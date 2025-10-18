<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoomImagesTableSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = DB::table('rooms')->get();

        foreach ($rooms as $room) {
            DB::table('room_images')->insert([
                [
                    'room_id' => $room->id,
                    'image_path' => "images/rooms/{$room->room_number}-1.jpg",
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'room_id' => $room->id,
                    'image_path' => "images/rooms/{$room->room_number}-2.jpg",
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
