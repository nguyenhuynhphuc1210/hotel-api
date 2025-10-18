<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'customer_id',
        'checkin_date',
        'checkout_date',
        'total_price',
        'deposit_amount',
        'remaining_amount',
        'status',
    ];

    /**
     * Quan hệ: Booking thuộc về 1 phòng
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Quan hệ: Booking thuộc về 1 khách hàng
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Quan hệ: Booking có nhiều dịch vụ thông qua bảng trung gian booking_services
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'booking_services')
            ->withPivot('quantity', 'total_price')
            ->withTimestamps();
    }

    /**
     * Quan hệ: Booking có 1 invoice
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
