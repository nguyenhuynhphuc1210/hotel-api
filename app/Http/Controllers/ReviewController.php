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
        // Kiểm tra người dùng có quyền đánh giá booking này không
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Bạn không có quyền đánh giá đơn này!'], 403);
        }

        // Kiểm tra đã có đánh giá chưa
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json(['message' => 'Bạn đã đánh giá đơn đặt phòng này rồi!'], 400);
        }

        // Validate
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Lưu review
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
