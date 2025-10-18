<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Lấy danh sách dịch vụ
     */
    public function index()
    {
        $services = Service::paginate(10);
        return response()->json($services);
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_name'        => 'required|string|max:255|unique:services',
            'price'       => 'required|numeric|min:0',
        ]);

        $service = Service::create($request->all());

        return response()->json($service, 201);
    }

    /**
     * Hiển thị thông tin dịch vụ
     */
    public function show(Service $service)
    {
        return response()->json($service);
    }

    /**
     * Cập nhật dịch vụ
     */
    public function update(Request $request, Service $service)
    {
        $request->validate([
            'service_name'        => 'sometimes|string|max:255|unique:services,service_name,' . $service->id,
            'price'       => 'sometimes|numeric|min:0',
        ]);

        $service->update($request->all());

        return response()->json($service);
    }

    /**
     * Xóa dịch vụ
     */
    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(null, 204);
    }

    public function allServices()
    {
        $services = Service::all(); // hoặc dùng ->get() cũng được
        return response()->json($services);
    }
}
