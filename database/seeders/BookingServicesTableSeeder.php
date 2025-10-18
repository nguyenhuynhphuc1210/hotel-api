<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Service;

class BookingServicesTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $serviceId = rand(1, 10);
            $quantity  = rand(1, 5);

            // Lấy giá dịch vụ từ bảng services
            $service   = Service::find($serviceId);
            $price     = $service ? $service->price : 0;
            $total     = $price * $quantity;

            DB::table('booking_services')->insert([
                'booking_id'  => rand(1, 10),
                'service_id'  => $serviceId,
                'quantity'    => $quantity,
                'total_price' => $total,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
