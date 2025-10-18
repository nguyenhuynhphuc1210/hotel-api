<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    // Cho phép gán giá trị hàng loạt (mass assignment)
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
    ];
}
