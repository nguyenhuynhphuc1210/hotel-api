<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-mail', function() {
    Mail::raw('Test gửi email từ Laravel', function($message){
        $message->to('nguyenhuynhphuc1210@gmail.com')
                ->subject('Test Email Laravel');
    });
    return 'Đã gửi thử email!';
});