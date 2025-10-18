<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class UserController extends Controller
{
    // -------------------- CRUD cơ bản --------------------
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($users);
    }

    public function show($id)
    {
        return response()->json(User::findOrFail($id));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fullname' => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:0,1,2',
        ]);

        $user = User::create([
            'fullname' => $request->fullname,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'fullname' => 'sometimes|required|string|max:255',
            'email'    => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role'     => 'sometimes|required|in:0,1,2',
        ]);

        $user->update([
            'fullname' => $request->fullname ?? $user->fullname,
            'email'    => $request->email ?? $user->email,
            'role'     => $request->role ?? $user->role,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        return response()->json($user);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    public function updateProfile(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'fullname' => 'sometimes|required|string|max:255',
            'email'    => 'sometimes|required|email|unique:users,email,' . $id,
        ]);

        $user->fullname = $request->fullname ?? $user->fullname;
        $user->email    = $request->email ?? $user->email;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    // -------------------- OTP & Quên mật khẩu --------------------
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Tạo OTP 6 chữ số
        $otp = rand(100000, 999999);
        $user->otp_code = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(5);
        $user->save();

        // Gửi email OTP
        Mail::raw("Mã OTP của bạn là: {$otp}. Mã này hết hạn sau 5 phút.", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Mã OTP xác thực quên mật khẩu')
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME')); // đảm bảo from hợp lệ
        });

        return response()->json(['message' => 'Đã gửi mã OTP đến email của bạn.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'otp_code' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user->otp_code || $user->otp_code !== $request->otp_code || Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['status' => 'error', 'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn!'], 400);
        }

        return response()->json(['status' => 'success', 'message' => 'OTP hợp lệ!']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email|exists:users,email',
            'otp_code'              => 'required|string',
            'password'              => 'required|string|min:6|confirmed', // yêu cầu password_confirmation
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user->otp_code || $user->otp_code !== $request->otp_code || Carbon::now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'Mã OTP không hợp lệ hoặc đã hết hạn.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        return response()->json(['message' => 'Đặt lại mật khẩu thành công!']);
    }
}
