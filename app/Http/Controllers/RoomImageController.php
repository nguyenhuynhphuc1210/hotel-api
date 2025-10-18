<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Cloudinary;
use Exception;

class RoomImageController extends Controller
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        // Khởi tạo Cloudinary từ CLOUDINARY_URL
        $this->cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
    }

    /**
     * Lấy danh sách ảnh của một phòng
     */
    public function index(Room $room)
    {
        // Trả về URL đầy đủ cho frontend
        $images = $room->images->map(function ($img) {
            return [
                'id'  => $img->id,
                'url' => $img->image_path, // đã lưu URL đầy đủ
            ];
        });

        return response()->json($images);
    }

    /**
     * Upload nhiều ảnh cho phòng
     */
    public function store(Request $request, Room $room)
    {
        $request->validate([
            'images'   => 'required|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $uploaded = [];

        foreach ($request->file('images') as $file) {
            try {
                // Upload lên Cloudinary
                $path = Storage::disk('cloudinary')->putFile('rooms', $file);

                // Chuyển public ID thành URL đầy đủ
                $url = $this->cloudinary->image($path)->toUrl();

                // Lưu DB
                $image = $room->images()->create([
                    'image_path' => $url,
                ]);

                $uploaded[] = [
                    'id'  => $image->id,
                    'url' => $url,
                ];
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload Cloudinary thất bại',
                    'error'   => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'message' => 'Upload thành công',
            'images'  => $uploaded,
        ], 201);
    }

    /**
     * Xóa 1 ảnh (chỉ xoá record DB, không xoá Cloudinary)
     */
    public function destroy(RoomImage $roomImage)
    {
        try {
            $roomImage->delete();
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa ảnh thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Ảnh đã được xóa'], 204);
    }
}
