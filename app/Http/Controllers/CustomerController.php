<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{

    public function index()
    {
        $customers = Customer::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fullname' => 'required|string|max:255',
            'phone'    => 'required|string|max:20|unique:customers',
            'email'    => 'required|email|unique:customers',
            'cccd'     => 'required|string|max:20|unique:customers',
        ]);

        $customer = Customer::create($request->all());

        return response()->json($customer, 201);
    }

    /**
     * Hiển thị thông tin một khách hàng theo id
     */
    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    /**
     * Cập nhật thông tin khách hàng
     */
    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'phone'    => 'sometimes|string|max:20|unique:customers,phone,' . $customer->id,
            'email'    => 'sometimes|email|unique:customers,email,' . $customer->id,
            'cccd'     => 'sometimes|string|max:20|unique:customers,cccd,' . $customer->id,
        ]);

        $customer->update($request->all());

        return response()->json($customer);
    }

    /**
     * Xóa khách hàng
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(null, 204);
    }
}
