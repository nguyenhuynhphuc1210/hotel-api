<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('room_id');
            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');

            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->dateTime('checkin_date');
            $table->dateTime('checkout_date');

            // Trạng thái: pending (chưa xử lý), deposit_paid (đã cọc), confirmed (đã xác nhận),
            // cancelled (hủy), completed (đã thanh toán đủ và hoàn tất)
            $table->enum('status', ['pending', 'deposit_paid', 'confirmed', 'cancelled', 'completed'])
                  ->default('pending');

            $table->decimal('total_price', 12, 2)->default(0);       // Tổng tiền
            $table->decimal('deposit_amount', 12, 2)->default(0);    // Tiền cọc (20%)
            $table->decimal('remaining_amount', 12, 2)->default(0);  // Số tiền còn lại (80%)

            $table->timestamps(); // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
