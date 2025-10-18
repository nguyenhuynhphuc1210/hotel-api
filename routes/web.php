<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-seeder', function () {
    try {
        // Chạy seeder cụ thể
        Artisan::call('db:seed', [
            '--force' => true, // Bắt buộc chạy
        ]);

        return "Seeder chạy thành công!";
    } catch (\Exception $e) {
        return "Lỗi khi chạy seeder: " . $e->getMessage();
    }
});