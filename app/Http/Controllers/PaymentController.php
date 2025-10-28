<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // Tạo VNPay URL
    public function createVNPay(Request $request)
    {
        $booking = Booking::create([
            'customer_id'      => $request->customer_id,
            'room_id'          => $request->room_id,
            'checkin_date'     => $request->checkin_date,
            'checkout_date'    => $request->checkout_date,
            'status'           => 'pending',
            'total_price'      => $request->total,
            'deposit_amount'   => 0,
            'remaining_amount' => $request->total,
        ]);

        if ($request->has('services') && is_array($request->services)) {
            foreach ($request->services as $serviceItem) {
                $service = Service::find($serviceItem['id']);
                if ($service) {
                    $booking->services()->attach($service->id, [
                        'quantity'    => $serviceItem['quantity'],
                        'total_price' => $serviceItem['price'] * $serviceItem['quantity'],
                    ]);
                }
            }
        }

        // 2️⃣ Chuẩn bị thông tin VNPay
        $vnp_TmnCode = env('VNP_TMN_CODE');
        $vnp_HashSecret = env('VNP_HASH_SECRET');
        $vnp_Url = env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $vnp_Returnurl = env('APP_URL_FRONTEND') . '/payment-success';

        $vnp_TxnRef = $booking->id; // dùng booking id làm reference
        $vnp_OrderInfo = 'Đặt cọc đặt phòng ID #' . $booking->id;
        $vnp_Amount = $request->amount * 100; // VNPay nhân 100
        $vnp_OrderType = 'billpayment';
        $vnp_Locale = 'vn';
        $vnp_BankCode = 'NCB';
        $vnp_IpAddr = $request->ip();

        $inputData = [
            "vnp_Version"   => "2.1.0",
            "vnp_TmnCode"   => $vnp_TmnCode,
            "vnp_Amount"    => $vnp_Amount,
            "vnp_Command"   => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode"  => "VND",
            "vnp_IpAddr"    => $vnp_IpAddr,
            "vnp_Locale"    => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef"    => $vnp_TxnRef,
            "vnp_BankCode"  => $vnp_BankCode
        ];

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url .= "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return response()->json([
            'code' => '00',
            'message' => 'success',
            'payUrl' => $vnp_Url
        ]);
    }

    public function vnpayReturn(Request $request)
    {
        $vnp_HashSecret = env('VNP_HASH_SECRET');
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        unset($inputData['vnp_SecureHashType']);
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);

        $hashData = '';
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        if ($secureHash != $vnp_SecureHash) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký không hợp lệ!'
            ]);
        }

        if ($request->vnp_ResponseCode == '00') {
            // Lấy booking theo vnp_TxnRef
            $booking = Booking::find($request->vnp_TxnRef);
            if (!$booking) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Booking không tồn tại!'
                ]);
            }

            // Cập nhật tiền cọc và trạng thái booking
            $booking->deposit_amount = $request->vnp_Amount / 100;
            $booking->remaining_amount = $booking->total_price - $booking->deposit_amount;
            $booking->save();

            // --- Tạo invoice ---
            $invoice = $booking->invoice;
            if (!$invoice) {
                $invoice = $booking->invoice()->create([
                    'total_amount' => $booking->total_price,
                    'payment_date' => now(),
                    'status'       => 'unpaid',
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Thanh toán và đặt phòng thành công!',
                'booking' => $booking->load(['services', 'room', 'customer']),
                'invoice' => $invoice
            ]);
        }

        return response()->json([
            'status' => 'fail',
            'message' => 'Thanh toán thất bại!',
            'data' => $request->all()
        ]);
    }

    public function payRemainingVNPay(Request $request)
    {
        $booking = Booking::find($request->booking_id);
        if (!$booking) {
            return response()->json(['code' => '01', 'message' => 'Booking không tồn tại']);
        }

        $amount = $booking->total_price - $booking->deposit_amount;
        if ($amount <= 0) {
            return response()->json(['code' => '02', 'message' => 'Booking đã thanh toán đầy đủ']);
        }

        // 1️⃣ Chuẩn bị thông tin VNPay
        $vnp_TmnCode = env('VNP_TMN_CODE');
        $vnp_HashSecret = env('VNP_HASH_SECRET');
        $vnp_Url = env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        $vnp_ReturnUrl = env('APP_URL_FRONTEND') . '/payment-remaining-success';

        $vnp_TxnRef = $booking->id . '_remain_' . time(); // reference cho lần thanh toán còn lại
        $vnp_OrderInfo = 'Thanh toán còn lại booking #' . $booking->id;
        $vnp_Amount = $amount * 100; // VNPay yêu cầu *100
        $vnp_OrderType = 'other';
        $vnp_BankCode = 'NCB';
        $vnp_Locale = 'vn';
        $vnp_IpAddr = request()->ip();

        $inputData = [
            "vnp_Version"   => "2.1.0",
            "vnp_TmnCode"   => $vnp_TmnCode,
            "vnp_Amount"    => $vnp_Amount,
            "vnp_Command"   => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode"  => "VND",
            "vnp_IpAddr"    => $vnp_IpAddr,
            "vnp_Locale"    => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef"    => $vnp_TxnRef,
            "vnp_BankCode"  => $vnp_BankCode,
        ];

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url .= "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return response()->json([
            'code' => '00',
            'message' => 'success',
            'payUrl' => $vnp_Url
        ]);
    }

    public function vnpayRemainingReturn(Request $request)
    {
        $vnp_HashSecret = env('VNP_HASH_SECRET');

        // Lấy tất cả params VNPay trả về
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        // Xóa params không dùng để hash
        unset($inputData['vnp_SecureHashType']);
        unset($inputData['vnp_SecureHash']);

        // Sắp xếp và tạo hashData giống vnpayReturn
        ksort($inputData);
        $hashData = '';
        $i = 0;
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        if ($secureHash !== $vnp_SecureHash) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký VNPay không hợp lệ!'
            ]);
        }

        if (($request->vnp_ResponseCode ?? '') === '00') {
            // Lấy booking theo vnp_TxnRef
            $txnRef = $request->vnp_TxnRef;
            $bookingId = explode('_', $txnRef)[0]; // nếu có _remain_ thì tách ID
            $booking = Booking::find($bookingId);

            if (!$booking) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Booking không tồn tại!'
                ]);
            }

            // Cập nhật deposit và remaining
            $booking->deposit_amount += $booking->remaining_amount; // trả hết số tiền còn lại
            $booking->remaining_amount = 0;
            $booking->save();

            // Cập nhật invoice nếu chưa có
            $invoice = $booking->invoice;
            if ($booking->remaining_amount <= 0) {
                $invoice->status = 'paid';
                $invoice->payment_date = now();
                $invoice->save();

                $booking->status = 'completed';
                $booking->save();

                $room = $booking->room;
                if ($room) {
                    $room->status = 'available';
                    $room->save();
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Thanh toán số tiền còn lại thành công!',
                'booking' => $booking->load(['services', 'room', 'customer']),
                'invoice' => $invoice
            ]);
        }

        return response()->json([
            'status' => 'fail',
            'message' => 'Thanh toán thất bại!',
            'data' => $request->all()
        ]);
    }

    public function createMoMo(Request $request)
    {
        // 1️⃣ Tạo booking tạm
        $booking = Booking::create([
            'customer_id'      => $request->customer_id,
            'room_id'          => $request->room_id,
            'checkin_date'     => $request->checkin_date,
            'checkout_date'    => $request->checkout_date,
            'status'           => 'pending',
            'total_price'      => $request->total,
            'deposit_amount'   => 0,
            'remaining_amount' => $request->total,
        ]);

        // 2️⃣ Gắn dịch vụ nếu có
        if ($request->has('services') && is_array($request->services)) {
            foreach ($request->services as $serviceItem) {
                $service = Service::find($serviceItem['id']);
                if ($service) {
                    $booking->services()->attach($service->id, [
                        'quantity'    => $serviceItem['quantity'],
                        'total_price' => $serviceItem['price'] * $serviceItem['quantity'],
                    ]);
                }
            }
        }

        // 3️⃣ Chuẩn bị dữ liệu MoMo
        $endpoint = env('MOMO_ENDPOINT'); // ví dụ: https://test-payment.momo.vn/gw_payment/transactionProcessor
        $partnerCode = env('MOMO_PARTNER_CODE');
        $accessKey = env('MOMO_ACCESS_KEY');
        $secretKey = env('MOMO_SECRET_KEY');
        $returnUrl = env('APP_URL_FRONTEND') . '/payment-success';
        $orderInfo = 'Đặt cọc phòng ID #' . $booking->id;
        $amount = $request->amount; // số tiền cọc
        $orderId = $booking->id . '_' . time();
        $requestId = $booking->id . '_' . time();
        $extraData = ''; // có thể thêm json info

        // 4️⃣ Tạo raw signature
        $rawHash = "accessKey=$accessKey&amount=$amount&extraData=$extraData&ipnUrl=$returnUrl&orderId=$orderId&orderInfo=$orderInfo&partnerCode=$partnerCode&redirectUrl=$returnUrl&requestId=$requestId&requestType=payWithATM";
        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        // 5️⃣ Payload gửi MoMo
        $data = [
            "partnerCode" => $partnerCode,
            "accessKey"   => $accessKey,
            "requestId"   => $requestId,
            "amount"      => (string)$amount,
            "orderId"     => $orderId,
            "orderInfo"   => $orderInfo,
            "redirectUrl" => $returnUrl,
            "ipnUrl"      => $returnUrl,
            "extraData"   => $extraData,
            "requestType" => "payWithATM",
            "signature"   => $signature,
        ];

        // 6️⃣ Gửi request tới MoMo
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $result = curl_exec($ch);
        $response = json_decode($result, true);

        if (isset($response['payUrl'])) {
            return response()->json([
                'code' => '00',
                'message' => 'success',
                'payUrl' => $response['payUrl']
            ]);
        }

        return response()->json([
            'code' => '01',
            'message' => 'Không thể khởi tạo thanh toán MoMo',
            'data' => $response
        ]);
    }

    public function momoReturn(Request $request)
    {
        $accessKey = env('MOMO_ACCESS_KEY');
        $secretKey = env('MOMO_SECRET_KEY');

        $data = $request->all();
        $signature = $data['signature'] ?? '';
        $resultCode = $data['resultCode'] ?? -1;
        $amount = $data['amount'] ?? 0;
        $orderId = $data['orderId'] ?? null;

        if (!$orderId) {
            return response()->json([
                'status' => 'error',
                'message' => 'orderId không tồn tại'
            ]);
        }

        // 1️⃣ Tạo raw signature để so sánh
        $rawHash = "accessKey=$accessKey&amount={$data['amount']}&extraData={$data['extraData']}&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";
        $checkSignature = hash_hmac("sha256", $rawHash, $secretKey);

        if ($checkSignature !== $signature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký MoMo không hợp lệ!'
            ]);
        }

        // 2️⃣ Kiểm tra resultCode
        if ($resultCode != 0) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Thanh toán MoMo thất bại!',
                'data' => $data
            ]);
        }

        // 3️⃣ Cập nhật booking
        // orderId có dạng {booking_id}_{timestamp}
        $bookingId = explode('_', $orderId)[0];
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking không tồn tại!'
            ]);
        }

        $booking->deposit_amount = $amount;
        $booking->remaining_amount = $booking->total_price - $booking->deposit_amount;
        $booking->save();

        // 4️⃣ Tạo invoice nếu chưa có
        $invoice = $booking->invoice;
        if (!$invoice) {
            $invoice = $booking->invoice()->create([
                'total_amount' => $booking->total_price,
                'payment_date' => now(),
                'status'       => 'unpaid',
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Thanh toán MoMo thành công!',
            'booking' => $booking->load(['services', 'room', 'customer']),
            'invoice' => $invoice
        ]);
    }

    public function payRemainingMoMo(Request $request)
    {
        $booking = Booking::find($request->booking_id);
        if (!$booking) {
            return response()->json(['code' => '01', 'message' => 'Booking không tồn tại']);
        }

        $amount = $booking->total_price - $booking->deposit_amount;
        if ($amount <= 0) {
            return response()->json(['code' => '02', 'message' => 'Booking đã thanh toán đầy đủ']);
        }

        $endpoint = env('MOMO_ENDPOINT');
        $partnerCode = env('MOMO_PARTNER_CODE');
        $accessKey = env('MOMO_ACCESS_KEY');
        $secretKey = env('MOMO_SECRET_KEY');
        $returnUrl = env('APP_URL_FRONTEND') . '/payment-remaining-success';
        $orderInfo = 'Thanh toán còn lại booking #' . $booking->id;

        $orderId = $booking->id . '_remain_' . time();
        $requestId = $booking->id . '_remain_' . time();
        $extraData = '';

        $rawHash = "accessKey=$accessKey&amount=$amount&extraData=$extraData&ipnUrl=$returnUrl&orderId=$orderId&orderInfo=$orderInfo&partnerCode=$partnerCode&redirectUrl=$returnUrl&requestId=$requestId&requestType=payWithATM";
        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        $data = [
            "partnerCode" => $partnerCode,
            "accessKey"   => $accessKey,
            "requestId"   => $requestId,
            "amount"      => (string)$amount,
            "orderId"     => $orderId,
            "orderInfo"   => $orderInfo,
            "redirectUrl" => $returnUrl,
            "ipnUrl"      => $returnUrl,
            "extraData"   => $extraData,
            "requestType" => "payWithATM",
            "signature"   => $signature,
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $result = curl_exec($ch);
        $response = json_decode($result, true);

        if (isset($response['payUrl'])) {
            return response()->json(['code' => '00', 'payUrl' => $response['payUrl']]);
        }

        return response()->json(['code' => '03', 'message' => 'Không thể tạo link MoMo', 'data' => $response]);
    }

    public function momoRemainingReturn(Request $request)
    {
        $accessKey = env('MOMO_ACCESS_KEY');
        $secretKey = env('MOMO_SECRET_KEY');

        $data = $request->all();
        $signature = $data['signature'] ?? '';
        $resultCode = $data['resultCode'] ?? -1;
        $amount = $data['amount'] ?? 0;
        $orderId = $data['orderId'] ?? null;

        if (!$orderId) {
            return response()->json([
                'status' => 'error',
                'message' => 'orderId không tồn tại'
            ]);
        }

        // 1️⃣ Tạo raw signature chuẩn MoMo
        $rawHash = "accessKey=$accessKey&amount={$data['amount']}&extraData={$data['extraData']}&message={$data['message']}&orderId={$data['orderId']}&orderInfo={$data['orderInfo']}&orderType={$data['orderType']}&partnerCode={$data['partnerCode']}&payType={$data['payType']}&requestId={$data['requestId']}&responseTime={$data['responseTime']}&resultCode={$data['resultCode']}&transId={$data['transId']}";
        $checkSignature = hash_hmac("sha256", $rawHash, $secretKey);

        if ($checkSignature !== $signature) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký MoMo không hợp lệ!'
            ]);
        }

        // 2️⃣ Kiểm tra resultCode
        if ($resultCode != 0) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Thanh toán MoMo thất bại!',
                'data' => $data
            ]);
        }

        // 3️⃣ Cập nhật booking
        $bookingId = explode('_', $orderId)[0];
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking không tồn tại!'
            ]);
        }

        // Cập nhật số tiền đã thanh toán và còn lại
        $booking->deposit_amount += $booking->remaining_amount; // trả hết số tiền còn lại
        $booking->remaining_amount = 0;
        $booking->save();

        // 4️⃣ Cập nhật trạng thái invoice nếu đã thanh toán đủ
        $invoice = $booking->invoice;
        if ($booking->remaining_amount <= 0 && $invoice) {
            $invoice->status = 'paid';
            $invoice->payment_date = now();
            $invoice->save();

            // Chỉ mark completed khi thanh toán xong
            $booking->status = 'completed';
            $booking->save();

            $room = $booking->room;
            if ($room) {
                $room->status = 'available';
                $room->save();
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Thanh toán MoMo thành công!',
            'booking' => $booking->load(['services', 'room', 'customer']),
            'invoice' => $invoice
        ]);
    }
}
