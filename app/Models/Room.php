<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_number',
        'type',
        'price',
        'status',
    ];

    /**
     * Quan hệ: 1 phòng có thể có nhiều booking
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function images()
    {
        return $this->hasMany(RoomImage::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
