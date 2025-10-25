<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function momoPayment(Request $request)
    {
        $endpoint = "https://test-payment.momo.vn/v2/gateway/api/create";

        $partnerCode = env('MOMO_PARTNER_CODE');
        $accessKey = env('MOMO_ACCESS_KEY');
        $secretKey = env('MOMO_SECRET_KEY');
        $orderInfo = $request->orderInfo;
        $amount = $request->amount;
        $orderId = time() . "";
        $redirectUrl = env('APP_URL_FRONTEND') . "/payment-success";
        $ipnUrl = env('APP_URL') . "/api/payment/momo/callback";

        // Dữ liệu gửi kèm để lưu booking sau callback
        $extraData = base64_encode(json_encode([
            'room_id' => $request->room_id,
            'customer_id' => $request->customer_id ?? null,
            'checkin_date' => $request->checkin_date,
            'checkout_date' => $request->checkout_date,
            'total_price' => $request->total,
            'deposit_amount' => $request->amount,
        ]));

        $requestId = time() . "";
        $requestType = "payWithATM";

        $rawHash = "accessKey=" . $accessKey .
            "&amount=" . $amount .
            "&extraData=" . $extraData .
            "&ipnUrl=" . $ipnUrl .
            "&orderId=" . $orderId .
            "&orderInfo=" . $orderInfo .
            "&partnerCode=" . $partnerCode .
            "&redirectUrl=" . $redirectUrl .
            "&requestId=" . $requestId .
            "&requestType=" . $requestType;

        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        $data = [
            'partnerCode' => $partnerCode,
            'partnerName' => 'MoMo Payment',
            'storeId' => 'MoMoTestStore',
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature
        ];

        $result = Http::post($endpoint, $data);
        return response()->json($result->json());
    }

    // ✅ Nhận callback từ MoMo sau khi thanh toán
    public function momoCallback(Request $request)
    {
        Log::info('MoMo callback:', $request->all());

        // Giải mã extraData
        $extraData = json_decode(base64_decode($request->extraData ?? ''), true);

        // Nếu thanh toán thành công (resultCode = 0)
        if ($request->resultCode == 0 && $extraData) {
            Booking::create([
                'room_id' => $extraData['room_id'],
                'customer_id' => $extraData['customer_id'],
                'checkin_date' => $extraData['checkin_date'],
                'checkout_date' => $extraData['checkout_date'],
                'status' => 'deposit_paid',
                'total_price' => $extraData['total_price'],
                'deposit_amount' => $extraData['deposit_amount'],
                'remaining_amount' => $extraData['total_price'] - $extraData['deposit_amount'],
            ]);
        }

        return response()->json(['message' => 'Callback processed']);
    }
}
