<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Booking;
use App\Models\Room;
use App\Models\BookingService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Lấy danh sách hóa đơn
     */
    public function index()
    {
        $invoices = Invoice::with('booking.customer', 'booking.room')
        ->orderBy('created_at', 'desc')
        ->paginate(10); // mỗi trang 10 hóa đơn

    // Thêm deposit_amount vào từng invoice trong trang hiện tại
    $invoices->getCollection()->transform(function ($invoice) {
        $invoice->deposit_amount = $invoice->booking->deposit_amount ?? 0;
        return $invoice;
    });

    return response()->json($invoices);
    }

    /**
     * Tạo mới hóa đơn
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'status'     => 'required|string|in:paid,unpaid,pending',
        ]);

        $booking = Booking::with('room')->findOrFail($request->booking_id);

        // Tính số ngày thuê
        $days = $booking->checkin_date && $booking->checkout_date
            ? max(1, $booking->checkout_date->diffInDays($booking->checkin_date))
            : 1;

        $roomCost = $booking->room ? ($booking->room->price * $days) : 0;

        // Tổng chi phí dịch vụ
        $serviceCost = BookingService::where('booking_id', $booking->id)->sum('total_price');

        $totalAmount = $roomCost + $serviceCost;

        $invoice = Invoice::create([
            'booking_id'   => $booking->id,
            'total_amount' => $totalAmount,
            'payment_date' => now(),
            'status'       => $request->status,
        ]);

        $invoice->load('booking.customer', 'booking.room');
        $invoice->deposit_amount = $booking->deposit_amount ?? 0;

        return response()->json($invoice, 201);
    }

    /**
     * Hiển thị chi tiết hóa đơn
     */
    public function show(Invoice $invoice)
    {
        $invoice->load('booking.customer', 'booking.room');
        $invoice->deposit_amount = $invoice->booking->deposit_amount ?? 0;

        return response()->json($invoice);
    }
    /**
     * Cập nhật hóa đơn
     */
    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'status' => 'sometimes|string|in:paid,unpaid,pending',
        ]);

        if ($request->has('status')) {
            $invoice->status = $request->status;
        }

        // Nếu muốn cập nhật lại số tiền (trong trường hợp dịch vụ thay đổi)
        if ($request->has('recalculate') && $request->recalculate == true) {
            $booking = $invoice->booking()->with('room')->first();

            $days = $booking->checkin_date && $booking->checkout_date
                ? max(1, $booking->checkout_date->diffInDays($booking->checkin_date))
                : 1;

            $roomCost = $booking->room ? ($booking->room->price * $days) : 0;
            $serviceCost = BookingService::where('booking_id', $booking->id)->sum('total_price');

            $invoice->total_amount = $roomCost + $serviceCost;
        }

        $invoice->save();

        $invoice->load('booking.customer', 'booking.room');
        $invoice->deposit_amount = $invoice->booking->deposit_amount ?? 0;

        return response()->json($invoice);
    }

    /**
     * Xóa hóa đơn
     */
    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return response()->json(null, 204);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        // Nếu đã thanh toán rồi thì trả về
        if ($invoice->status === 'paid') {
            return response()->json([
                'message' => 'Hóa đơn đã được thanh toán'
            ], 400);
        }

        // Cập nhật trạng thái
        $invoice->status = 'paid';
        $invoice->payment_date = now();
        $invoice->save();

        $booking = $invoice->booking;
        $booking->status = 'completed';
        $booking->save();

        $room = $booking->room;
        if ($room) {
            $room->status = 'available';
            $room->save();
        }

        $invoice->load('booking.customer', 'booking.room');
        $invoice->deposit_amount = $invoice->booking->deposit_amount ?? 0;

        return response()->json($invoice);
    }
}
