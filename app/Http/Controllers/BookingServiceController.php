<?php

namespace App\Http\Controllers;

use App\Models\BookingService;
use App\Models\Service;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingServiceController extends Controller
{
    /**
     * Danh sách tất cả dịch vụ gắn với booking
     */
    public function index()
    {
        $bookingServices = BookingService::with(['booking', 'service'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json($bookingServices);
    }

    /**
     * Gán dịch vụ vào booking
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'service_id' => 'required|exists:services,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $service = Service::findOrFail($request->service_id);
        $totalPrice = $service->price * $request->quantity;

        $bookingService = BookingService::create([
            'booking_id'  => $request->booking_id,
            'service_id'  => $request->service_id,
            'quantity'    => $request->quantity,
            'total_price' => $totalPrice,
        ]);

        // 🟢 Cập nhật tổng tiền booking + invoice
        $this->updateBookingTotals($request->booking_id);

        return response()->json($bookingService->load(['booking', 'service']), 201);
    }

    /**
     * Xem chi tiết dịch vụ đã gắn vào booking
     */
    public function show(BookingService $bookingService)
    {
        return response()->json($bookingService->load(['booking', 'service']));
    }

    /**
     * Cập nhật số lượng dịch vụ
     */
    public function update(Request $request, BookingService $bookingService)
    {
        $request->validate([
            'quantity' => 'sometimes|integer|min:1',
        ]);

        if ($request->has('quantity')) {
            $service = $bookingService->service;
            $bookingService->quantity = $request->quantity;
            $bookingService->total_price = $service->price * $request->quantity;
        }

        $bookingService->save();

        // 🟢 Cập nhật tổng tiền booking + invoice
        $this->updateBookingTotals($bookingService->booking_id);

        return response()->json($bookingService->load(['booking', 'service']));
    }

    /**
     * Xóa dịch vụ khỏi booking
     */
    public function destroy(BookingService $bookingService)
    {
        $bookingId = $bookingService->booking_id;
        $bookingService->delete();

        // 🟢 Cập nhật lại tổng tiền booking + invoice
        $this->updateBookingTotals($bookingId);

        return response()->json(null, 204);
    }

    /**
     * Hàm helper: cập nhật lại tổng tiền booking + invoice
     */
    private function updateBookingTotals($bookingId)
    {
        $booking = Booking::with('services')->findOrFail($bookingId);

        // Tổng tiền phòng
        $checkin  = new \DateTime($booking->checkin_date);
        $checkout = new \DateTime($booking->checkout_date);
        $nights   = $checkin->diff($checkout)->days;
        $roomTotal = $booking->room->price * $nights;

        // Tổng tiền dịch vụ
        $servicesTotal = $booking->services->sum('pivot.total_price');

        // Tổng cộng
        $total = $roomTotal + $servicesTotal;
        $deposit   = $total * 0.2;
        $remaining = $total - $deposit;

        // Cập nhật booking
        $booking->update([
            'total_price'     => $total,
            'deposit_amount'  => $deposit,
            'remaining_amount'=> $remaining,
        ]);

        // Cập nhật invoice
        if ($booking->invoice) {
            $booking->invoice->update([
                'total_amount' => $total,
            ]);
        }
    }
}
