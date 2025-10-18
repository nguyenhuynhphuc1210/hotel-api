<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomImageController extends Controller
{
    /**
     * Lấy danh sách ảnh của một phòng
     */
    public function index(Room $room)
    {
        return response()->json($room->images);
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
                // Upload lên Cloudinary chuẩn: dùng putFile
                $path = Storage::disk('cloudinary')->putFile('rooms', $file);

                // Lưu DB
                $image = $room->images()->create([
                    'image_path' => $path, // lưu URL Cloudinary
                ]);

                $uploaded[] = [
                    'id'  => $image->id,
                    'url' => $path,
                ];
            } catch (\Exception $e) {
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
            // Chỉ xoá database record
            $roomImage->delete();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa ảnh thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => 'Ảnh đã được xóa'], 204);
    }
}
