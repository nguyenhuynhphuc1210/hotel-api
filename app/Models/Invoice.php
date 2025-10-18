<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'total_amount',
        'payment_date',
        'status',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
