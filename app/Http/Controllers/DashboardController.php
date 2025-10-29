<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function revenue(Request $request)
    {
        $period = $request->query('period', 'day');
        $query = Invoice::where('status', 'paid');

        switch ($period) {
            case 'year':
                $data = $query
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('SUM(total_amount) as revenue')
                    )
                    ->groupBy('year')
                    ->orderBy('year')
                    ->get()
                    ->map(fn($item) => ['date' => $item->year, 'revenue' => $item->revenue]);
                break;

            case 'week':
                $data = $query
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('WEEK(created_at) as week'),
                        DB::raw('SUM(total_amount) as revenue')
                    )
                    ->groupBy('year', 'week')
                    ->orderBy('year')
                    ->orderBy('week')
                    ->get()
                    ->map(fn($item) => ['date' => "Tuần {$item->week}-{$item->year}", 'revenue' => $item->revenue]);
                break;

            case 'month':
                $data = $query
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('SUM(total_amount) as revenue')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get()
                    ->map(fn($item) => ['date' => "{$item->month}/{$item->year}", 'revenue' => $item->revenue]);
                break;

            case 'day':
            default:
                $data = $query
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('SUM(total_amount) as revenue')
                    )
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->map(fn($item) => ['date' => $item->date, 'revenue' => $item->revenue]);
                break;
        }

        return response()->json($data);
    }
}
