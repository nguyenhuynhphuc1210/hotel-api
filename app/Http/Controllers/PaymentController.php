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

    public function createVNPay(Request $request)
    {
        $vnp_TmnCode = env('VNP_TMN_CODE'); // Mã website tại VNPay
        $vnp_HashSecret = env('VNP_HASH_SECRET'); // Chuỗi bí mật
        $vnp_Url = env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'); // URL sandbox
        $vnp_Returnurl = env('VNP_RETURN_URL', 'http://localhost:5173/payment-success'); // Link frontend nhận kết quả

        $vnp_TxnRef = time(); // Mã đơn hàng (duy nhất)
        $vnp_OrderInfo = $request->orderInfo ?? 'Thanh toán VNPay';
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $request->amount * 100;
        $vnp_Locale = 'vn';
        $vnp_BankCode = 'NCB';
        $vnp_IpAddr = $request->ip();

        // Dữ liệu gửi lên VNPay
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_BankCode" => $vnp_BankCode
        );

        // Sắp xếp dữ liệu theo key
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

        $vnp_Url = $vnp_Url . "?" . $query;
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

    // 🔹 Xử lý kết quả trả về từ VNPay
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

        if ($secureHash == $vnp_SecureHash) {
            if ($request->vnp_ResponseCode == '00') {
                // ✅ Thanh toán thành công → tạo booking

                try {
                    $total = $request->total;
                    $deposit = $request->amount;

                    $booking = Booking::create([
                        'customer_id'      => $request->customer_id,
                        'room_id'          => $request->room_id,
                        'checkin_date'     => $request->checkin_date,
                        'checkout_date'    => $request->checkout_date,
                        'status'           => 'deposit_paid',
                        'total_price'      => $total,
                        'deposit_amount'   => $deposit,
                        'remaining_amount' => $total - $deposit,
                    ]);

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Thanh toán và đặt phòng thành công!',
                        'booking' => $booking,
                        'data'    => $request->all()
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Thanh toán thành công nhưng lỗi khi lưu booking',
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                // ❌ Thanh toán thất bại
                return response()->json([
                    'status' => 'fail',
                    'message' => 'Thanh toán thất bại!',
                    'data' => $request->all()
                ]);
            }
        } else {
            // ⚠️ Sai checksum
            return response()->json([
                'status' => 'error',
                'message' => 'Chữ ký không hợp lệ!'
            ]);
        }
    }
}
