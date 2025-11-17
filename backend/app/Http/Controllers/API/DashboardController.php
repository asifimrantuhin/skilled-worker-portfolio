<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use App\Models\Inquiry;
use App\Models\Ticket;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->adminDashboard($request);
        } elseif ($user->isAgent()) {
            return $this->agentDashboard($request);
        } else {
            return $this->customerDashboard($request);
        }
    }

    private function customerDashboard(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_bookings' => Booking::where('user_id', $user->id)->count(),
            'upcoming_bookings' => Booking::where('user_id', $user->id)
                ->where('travel_date', '>=', now())
                ->where('status', 'confirmed')
                ->count(),
            'pending_bookings' => Booking::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count(),
            'total_spent' => Booking::where('user_id', $user->id)
                ->where('payment_status', 'paid')
                ->sum('total_amount'),
        ];

        $upcomingBookings = Booking::where('user_id', $user->id)
            ->where('travel_date', '>=', now())
            ->where('status', 'confirmed')
            ->with('package')
            ->orderBy('travel_date', 'asc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'upcoming_bookings' => $upcomingBookings,
        ]);
    }

    private function agentDashboard(Request $request)
    {
        $agent = $request->user();

        $stats = [
            'total_bookings' => Booking::where('agent_id', $agent->id)->count(),
            'pending_bookings' => Booking::where('agent_id', $agent->id)->where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('agent_id', $agent->id)->where('status', 'confirmed')->count(),
            'total_commission' => \App\Models\AgentCommission::where('agent_id', $agent->id)
                ->where('status', 'paid')
                ->sum('commission_amount'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    private function adminDashboard(Request $request)
    {
        $stats = [
            'total_bookings' => Booking::count(),
            'total_packages' => Package::count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_agents' => User::where('role', 'agent')->count(),
            'total_revenue' => Payment::where('status', 'completed')->sum('amount'),
            'pending_inquiries' => Inquiry::where('status', 'new')->count(),
            'open_tickets' => Ticket::where('status', 'open')->count(),
        ];

        $recentBookings = Booking::with(['package', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $monthlyRevenue = Payment::where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'monthly_revenue' => $monthlyRevenue,
            'recent_bookings' => $recentBookings,
        ]);
    }

    public function adminStats(Request $request)
    {
        $period = $request->get('period', 'month'); // day, week, month, year

        $dateFrom = match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->subWeek()->startOfDay(),
            'month' => now()->subMonth()->startOfDay(),
            'year' => now()->subYear()->startOfDay(),
            default => now()->subMonth()->startOfDay(),
        };

        $stats = [
            'bookings' => Booking::where('created_at', '>=', $dateFrom)->count(),
            'revenue' => Payment::where('status', 'completed')
                ->where('created_at', '>=', $dateFrom)
                ->sum('amount'),
            'new_customers' => User::where('role', 'customer')
                ->where('created_at', '>=', $dateFrom)
                ->count(),
            'packages_sold' => Booking::where('created_at', '>=', $dateFrom)
                ->distinct('package_id')
                ->count('package_id'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'period' => $period,
        ]);
    }
}

