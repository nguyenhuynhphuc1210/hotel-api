<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'fullname',
        'phone',
        'email',
        'cccd',
    ];

    /**
     * Quan hệ: 1 khách hàng có nhiều booking
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'customer_id', 'id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
