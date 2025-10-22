<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking)
    {

        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json(['message' => 'Bạn đã đánh giá đơn đặt phòng này rồi!'], 400);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = Review::create([
            'user_id' => Auth::id(),
            'booking_id' => $booking->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Đánh giá thành công!',
            'review' => $review,
        ], 201);
    }
}
