<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string',
        ]);

        Contact::create($request->all());

        return response()->json(['message' => 'Cảm ơn bạn! Chúng tôi đã nhận được tin nhắn.']);
    }

    public function index()
    {
        $contacts = Contact::latest()->paginate(10);
        return response()->json($contacts);
    }

    // Xem chi tiết 1 tin nhắn
    public function show($id)
    {
        $contact = Contact::findOrFail($id);
        return response()->json($contact);
    }

    // Xóa tin nhắn
    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return response()->json(['message' => 'Tin nhắn đã được xóa thành công']);
    }
}
