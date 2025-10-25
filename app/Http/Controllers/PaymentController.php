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
        Log::info('--- CALLBACK START ---');
        Log::info('Raw content:', [$request->getContent()]);
        Log::info('Parsed data:', $request->all());

        $data = $request->all();
        if (empty($data)) parse_str($request->getContent(), $data);
        Log::info('Final data:', $data);

        $extraData = json_decode(base64_decode($data['extraData'] ?? ''), true);
        Log::info('Decoded extraData:', $extraData);

        $resultCode = $data['resultCode'] ?? null;
        Log::info('ResultCode:', [$resultCode]);

        if ($resultCode == 0 && $extraData) {
            try {
                Log::info('Creating booking record...', $extraData);

                Booking::create([
                    'room_id' => $extraData['room_id'] ?? null,
                    'customer_id' => $extraData['customer_id'] ?? null,
                    'checkin_date' => $extraData['checkin_date'] ?? null,
                    'checkout_date' => $extraData['checkout_date'] ?? null,
                    'status' => 'deposit_paid',
                    'total_price' => $extraData['total_price'] ?? 0,
                    'deposit_amount' => $extraData['deposit_amount'] ?? 0,
                    'remaining_amount' => ($extraData['total_price'] ?? 0) - ($extraData['deposit_amount'] ?? 0),
                ]);

                Log::info('Booking created successfully!');
            } catch (\Exception $e) {
                Log::error('Booking create failed:', [$e->getMessage()]);
            }
        } else {
            Log::warning('MoMo callback ignored: resultCode != 0 or no extraData', $data);
        }

        return response()->json(['message' => 'Callback processed']);
    }
}
