<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Cloudinary\Cloudinary;
use Exception;

class RoomController extends Controller
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        // Khởi tạo Cloudinary từ CLOUDINARY_URL
        $this->cloudinary = new Cloudinary(env('CLOUDINARY_URL'));
    }

    /**
     * Hiển thị danh sách tất cả các phòng
     */
    public function index(Request $request)
    {
        $query = Room::with('images')->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('all')) {
            return response()->json($query->get());
        }

        return response()->json($query->paginate(9));
    }

    /**
     * Tạo mới một phòng
     */
    public function store(Request $request)
    {
        $request->validate([
            'room_number' => 'required|string|max:50|unique:rooms',
            'type'        => 'required|string|max:100',
            'price'       => 'required|numeric|min:0',
            'status'      => 'required|in:available,booked,cleaning',
            'images'      => 'array',
            'images.*'    => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $room = Room::create($request->only(['room_number', 'type', 'price', 'status']));

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                try {
                    // Upload ảnh lên Cloudinary
                    $path = Storage::disk('cloudinary')->putFile('rooms', $image);

                    // Chuyển public ID thành URL đầy đủ
                    $url = $this->cloudinary->image($path)->toUrl();

                    $room->images()->create(['image_path' => $url]);
                } catch (Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Upload Cloudinary thất bại',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }
        }

        return response()->json($room->load('images'), 201);
    }

    /**
     * Hiển thị thông tin chi tiết một phòng
     */
    public function show(Room $room)
    {
        return response()->json($room->load('images'));
    }

    /**
     * Cập nhật thông tin phòng
     */
    public function update(Request $request, Room $room)
    {
        $request->validate([
            'room_number'    => 'sometimes|string|max:50|unique:rooms,room_number,' . $room->id,
            'type'           => 'sometimes|string|max:100',
            'price'          => 'sometimes|numeric|min:0',
            'status'         => 'sometimes|in:available,booked,cleaning',
            'new_images'     => 'array',
            'new_images.*'   => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'deleted_images' => 'array',
            'deleted_images.*' => 'integer|exists:room_images,id',
        ]);

        $room->update($request->only(['room_number', 'type', 'price', 'status']));

        // Xóa ảnh cũ trong DB
        if ($request->filled('deleted_images')) {
            $room->images()->whereIn('id', $request->deleted_images)->delete();
        }

        // Thêm ảnh mới lên Cloudinary
        if ($request->hasFile('new_images')) {
            foreach ($request->file('new_images') as $image) {
                try {
                    $path = Storage::disk('cloudinary')->putFile('rooms', $image);
                    $url = $this->cloudinary->image($path)->toUrl();

                    $room->images()->create(['image_path' => $url]);
                } catch (Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Upload Cloudinary thất bại',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }
        }

        return response()->json($room->load('images'));
    }

    /**
     * Xóa phòng (chỉ xoá database, không xoá Cloudinary)
     */
    public function destroy(Room $room)
    {
        $room->images()->delete();
        $room->delete();

        return response()->json(null, 204);
    }

    /**
     * Lấy tất cả phòng (không phân trang)
     */
    public function allRooms()
    {
        return response()->json(Room::with('images')->get());
    }

    public function getReviews($id)
    {
        $room = Room::with(['reviews.user'])->findOrFail($id);

        return response()->json([
            'reviews' => $room->reviews->map(function ($review) {
                return [
                    'user' => $review->user->fullname ?? 'Ẩn danh',
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at->format('d/m/Y'),
                ];
            }),
            'average_rating' => round($room->reviews->avg('rating'), 1),
        ]);
    }
}
