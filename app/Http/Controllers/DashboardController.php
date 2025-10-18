<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function index()
    {
        $paidInvoices = Invoice::where('status', 'paid')->get();

        return response()->json([
            'rooms'     => Room::count(),
            'customers' => Customer::count(),
            'bookings'  => Booking::count(),
            'invoices'  => $paidInvoices->count(),
            'revenue'   => $paidInvoices->sum('total_amount'),
        ]);
    }
}
