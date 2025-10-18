<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use App\Models\Customer;
use App\Models\Service;
use App\Models\BookingService;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    /**
     * Lấy danh sách tất cả booking (kèm room + customer + services + invoice)
     */
    public function index()
    {
        $bookings = Booking::with(['room', 'customer', 'services', 'invoice'])
            ->paginate(10);

        return response()->json($bookings);
    }

    /**
     * Tạo mới booking (kèm dịch vụ nếu có, tự động lưu customer nếu chưa có)
     */
    public function store(Request $request)
    {
        $request->validate([
            'fullname'       => 'required|string|max:255',
            'phone'          => 'required|string|max:20',
            'email'          => 'required|email',
            'cccd'           => 'required|string|max:20',
            'checkin_date'   => 'required|date',
            'checkout_date'  => 'required|date|after:checkin_date',
            'room_id'        => 'required|exists:rooms,id',
            'services'       => 'nullable|array',
            'services.*.id'  => 'exists:services,id',
            'services.*.quantity' => 'integer|min:1',
        ]);

        // 🟢 Kiểm tra phòng
        $room = Room::findOrFail($request->room_id);
        if ($room->status !== 'available') {
            return response()->json(['message' => 'Phòng hiện không khả dụng!'], 400);
        }

        // 🟢 Tính số đêm
        $checkin  = new \DateTime($request->checkin_date);
        $checkout = new \DateTime($request->checkout_date);
        $nights   = $checkin->diff($checkout)->days;
        if ($nights <= 0) {
            return response()->json(['message' => 'Ngày trả phòng phải sau ngày nhận phòng!'], 400);
        }

        // 🟢 Tìm hoặc tạo khách hàng
        $customer = Customer::firstOrCreate(
            ['email' => $request->email],
            [
                'fullname' => $request->fullname,
                'phone'    => $request->phone,
                'cccd'     => $request->cccd,
            ]
        );

        // 🟢 Tính tiền phòng
        $roomTotal = $room->price * $nights;

        // 🟢 Tính dịch vụ
        $servicesTotal = 0;
        $serviceData = [];
        if ($request->has('services')) {
            foreach ($request->services as $s) {
                $service = Service::find($s['id']);
                if ($service) {
                    $total = $service->price * $s['quantity'];
                    $servicesTotal += $total;

                    $serviceData[] = [
                        'booking_id'  => null, // sẽ gán sau khi có booking_id
                        'service_id'  => $s['id'],
                        'quantity'    => $s['quantity'],
                        'total_price' => $total,
                    ];
                }
            }
        }


        $totalAmount = $roomTotal + $servicesTotal;
        $deposit     = $totalAmount * 0.2;
        $remaining   = $totalAmount - $deposit;

        $booking = Booking::create([
            'room_id'         => $room->id,
            'customer_id'     => $customer->id,
            'checkin_date'    => $request->checkin_date,
            'checkout_date'   => $request->checkout_date,
            'total_price'     => $totalAmount,
            'deposit_amount'  => $deposit,
            'remaining_amount' => $remaining,
            'status'          => 'pending',
        ]);

        // Cập nhật lại booking_id cho serviceData
        foreach ($serviceData as &$sd) {
            $sd['booking_id'] = $booking->id;
            BookingService::create($sd);
        }

        // 🟢 Cập nhật trạng thái phòng
        $room->update(['status' => 'booked']);

        // 🟢 Tạo invoice
        $invoice = Invoice::create([
            'booking_id'   => $booking->id,
            'total_amount' => $totalAmount,
            'payment_date' => now(),
            'status'       => 'unpaid',
        ]);

        return response()->json([
            'message' => 'Đặt phòng thành công!',
            'booking' => $booking->load(['room', 'customer', 'services']),
            'invoice' => $invoice,
        ], 201);
    }

    /**
     * Hiển thị thông tin 1 booking
     */
    public function show(Booking $booking)
    {
        return response()->json(
            $booking->load(['room', 'customer', 'services', 'invoice'])
        );
    }

    /**
     * Cập nhật booking (tính lại số đêm, tiền phòng, invoice)
     */
    public function update(Request $request, Booking $booking)
    {
        $request->validate([
            'room_id'       => 'sometimes|exists:rooms,id',
            'customer_id'   => 'sometimes|exists:customers,id',
            'checkin_date'  => 'sometimes|date',
            'checkout_date' => 'sometimes|date|after:checkin_date',
            'status'        => 'sometimes|string|max:50',
        ]);

        $room = $request->has('room_id')
            ? Room::findOrFail($request->room_id)
            : $booking->room;

        // Nếu có thay đổi ngày thì tính lại số đêm và tiền phòng
        $roomTotal = $booking->total_price;
        if ($request->has('checkin_date') || $request->has('checkout_date')) {
            $checkin  = new \DateTime($request->checkin_date ?? $booking->checkin_date);
            $checkout = new \DateTime($request->checkout_date ?? $booking->checkout_date);
            $nights   = $checkin->diff($checkout)->days;

            if ($nights <= 0) {
                return response()->json(['message' => 'Ngày trả phòng phải sau ngày nhận phòng!'], 400);
            }

            $roomTotal = $room->price * $nights;
        }

        // Tổng dịch vụ
        $servicesTotal = $booking->services->sum('pivot.total_price');
        $totalAmount   = $roomTotal + $servicesTotal;
        $deposit       = $totalAmount * 0.2;
        $remaining     = $totalAmount - $deposit;

        // Cập nhật booking
        $booking->update([
            'room_id'         => $request->room_id ?? $booking->room_id,
            'customer_id'     => $request->customer_id ?? $booking->customer_id,
            'checkin_date'    => $request->checkin_date ?? $booking->checkin_date,
            'checkout_date'   => $request->checkout_date ?? $booking->checkout_date,
            'total_price'     => $totalAmount,
            'deposit_amount'  => $deposit,
            'remaining_amount' => $remaining,
            'status'          => $request->status ?? $booking->status,
        ]);

        // Cập nhật invoice
        if ($booking->invoice) {
            $booking->invoice->update([
                'total_amount' => $totalAmount,
            ]);
        }

        return response()->json($booking->load(['room', 'customer', 'services', 'invoice']));
    }

    /**
     * Xóa booking (trả phòng về available)
     */
    public function destroy(Booking $booking)
    {
        if ($booking->room) {
            $booking->room->update(['status' => 'available']);
        }

        $booking->services()->detach();
        if ($booking->invoice) {
            $booking->invoice->delete();
        }

        $booking->delete();

        return response()->json(null, 204);
    }

    public function myBookings()
    {
        // Lấy user hiện tại
        $user = Auth::user();

        // Kiểm tra user có liên kết với customer không
        $customer = $user->customer;
        if (!$customer) {
            return response()->json(['message' => 'Không tìm thấy thông tin khách hàng!'], 404);
        }

        // Lấy tất cả booking của customer kèm room, services, invoice
        $bookings = $customer->bookings()
            ->with(['room', 'services', 'invoice'])
            ->get();

        return response()->json($bookings);
    }

    public function cancelBooking(Booking $booking)
    {
        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking đã bị hủy trước đó!'], 400);
        }

        // Cập nhật trạng thái booking
        $booking->update(['status' => 'cancelled']);

        // Trả lại phòng về trạng thái available
        if ($booking->room) {
            $booking->room->update(['status' => 'available']);
        }

        // Cập nhật invoice nếu muốn
        if ($booking->invoice) {
            $booking->invoice->update(['status' => 'cancelled']);
        }

        return response()->json([
            'message' => 'Booking đã được hủy!',
            'booking' => $booking->load(['room', 'services', 'invoice'])
        ]);
    }

    public function confirm($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->status = 'confirmed';
        $booking->save();

        // Cập nhật phòng tương ứng sang trạng thái 'booked'
        $booking->room->update(['status' => 'booked']);

        return response()->json(['message' => 'Booking confirmed successfully']);
    }

    public function myBookingDetail($id)
    {
        // Lấy user hiện tại
        $user = Auth::user();

        // Kiểm tra user có liên kết với customer không
        $customer = $user->customer;
        if (!$customer) {
            return response()->json(['message' => 'Không tìm thấy thông tin khách hàng!'], 404);
        }

        // Lấy booking theo id, thuộc customer hiện tại
        $booking = $customer->bookings()
            ->with(['room', 'services', 'invoice', 'customer'])
            ->find($id);

        if (!$booking) {
            return response()->json(['message' => 'Booking không tồn tại hoặc không thuộc bạn!'], 404);
        }

        return response()->json($booking);
    }

    public function allBookings()
    {
        $bookings = Booking::with(['room', 'customer', 'services', 'invoice'])
            ->orderByDesc('id')
            ->get();

        return response()->json($bookings);
    }
}
