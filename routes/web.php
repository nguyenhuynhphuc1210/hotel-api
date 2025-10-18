<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mail', function () {
    try {
        Mail::raw('Email test từ Laravel trên Render', function ($message) {
            $message->to('nguyenhuynhphuc1210@gmail.com')
                    ->subject('Test Email Laravel Render');
        });

        return 'Email đã gửi thành công!';
    } catch (\Exception $e) {
        return 'Lỗi gửi email: ' . $e->getMessage();
    }
});