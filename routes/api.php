<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    UserController,
    CustomerController,
    RoomController,
    BookingController,
    ServiceController,
    BookingServiceController,
    InvoiceController,
    DashboardController,
    RoomImageController,
    ContactController,
    AuthController,
    ReviewController,
    PaymentController

};

// ------------------ PUBLIC ROUTES (không cần login) ------------------
Route::post('/payment/momo', [PaymentController::class, 'createMoMo']);
Route::post('/payment/momo/return', [PaymentController::class, 'momoReturn']);
Route::post('/payment/momo/pay-remaining', [PaymentController::class, 'payRemainingMoMo']);
Route::post('/payment/momo/remaining-return', [PaymentController::class, 'momoRemainingReturn']);


Route::post('/payment/vnpay', [PaymentController::class, 'createVNPay']);
Route::post('/payment/vnpay/return', [PaymentController::class, 'vnpayReturn']);
Route::post('/payment/vnpay/pay-remaining', [PaymentController::class, 'payRemainingVNPay']);
Route::post('/payment/vnpay/remaining-return', [PaymentController::class, 'vnpayRemainingReturn']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [UserController::class, 'sendOtp']);
Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);
Route::post('/contact', [ContactController::class, 'store']);
Route::get('/rooms/all', [RoomController::class, 'allRooms']);
Route::get('/services/all', [ServiceController::class, 'allServices']);
Route::get('/bookings/all', [BookingController::class, 'allBookings']);
// Xem phòng
Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);
Route::get('/rooms/{room}/images', [RoomImageController::class, 'index']);
Route::get('/rooms/{id}/reviews', [RoomController::class, 'getReviews']);


Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);

Route::post('/bookings', [BookingController::class, 'store']);
Route::post('/booking-services', [BookingServiceController::class, 'store']);

// ------------------ PROTECTED ROUTES (cần login) ------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/my-bookings/{booking}/review', [ReviewController::class, 'store']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/my-bookings', [BookingController::class, 'myBookings']);
    Route::get('/my-bookings/{id}', [BookingController::class, 'myBookingDetail']);
    Route::delete('/bookings/{booking}/cancel', [BookingController::class, 'cancelBooking']);
    Route::patch('/invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
    Route::patch('/change-password', [AuthController::class, 'changePassword']);
    Route::patch('/users/{user}/update-profile', [UserController::class, 'updateProfile']);
    Route::apiResource('customers', CustomerController::class);


    // ADMIN ONLY (role = 0)
    Route::middleware('role:0')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('dashboard', [DashboardController::class, 'index']);
    });

    // STAFF + ADMIN (role = 0,1)
    Route::middleware('role:0,1')->group(function () {
        Route::put('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
        
        Route::apiResource('bookings', BookingController::class)->except(['store']);
        Route::apiResource('booking-services', BookingServiceController::class)->except(['store']);
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
        Route::apiResource('rooms', RoomController::class)->except(['index', 'show']);

        Route::post('rooms/{room}/images', [RoomImageController::class, 'store']);
        Route::delete('room-images/{roomImage}', [RoomImageController::class, 'destroy']);

        Route::get('/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/{id}', [ContactController::class, 'show']);
        Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
    });
});
