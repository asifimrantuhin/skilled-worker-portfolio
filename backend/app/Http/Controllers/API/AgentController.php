<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\AgentCommission;
use App\Models\User;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function dashboard(Request $request)
    {
        $agent = $request->user();

        $stats = [
            'total_bookings' => Booking::where('agent_id', $agent->id)->count(),
            'pending_bookings' => Booking::where('agent_id', $agent->id)->where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('agent_id', $agent->id)->where('status', 'confirmed')->count(),
            'total_commission' => AgentCommission::where('agent_id', $agent->id)
                ->where('status', 'paid')
                ->sum('commission_amount'),
            'pending_commission' => AgentCommission::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->sum('commission_amount'),
            'total_customers' => Booking::where('agent_id', $agent->id)
                ->distinct('user_id')
                ->count('user_id'),
        ];

        $recentBookings = Booking::where('agent_id', $agent->id)
            ->with(['package', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_bookings' => $recentBookings,
        ]);
    }

    public function bookings(Request $request)
    {
        $agent = $request->user();

        $bookings = Booking::where('agent_id', $agent->id)
            ->with(['package', 'user', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
        ]);
    }

    public function commissions(Request $request)
    {
        $agent = $request->user();

        $commissions = AgentCommission::where('agent_id', $agent->id)
            ->with(['booking.package', 'booking.user'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $summary = [
            'total_commission' => AgentCommission::where('agent_id', $agent->id)
                ->where('status', 'paid')
                ->sum('commission_amount'),
            'pending_commission' => AgentCommission::where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->sum('commission_amount'),
            'total_bookings' => AgentCommission::where('agent_id', $agent->id)->count(),
        ];

        return response()->json([
            'success' => true,
            'commissions' => $commissions,
            'summary' => $summary,
        ]);
    }

    public function customers(Request $request)
    {
        $agent = $request->user();

        $customerIds = Booking::where('agent_id', $agent->id)
            ->distinct()
            ->pluck('user_id');

        $customers = User::whereIn('id', $customerIds)
            ->withCount(['bookings' => function($query) use ($agent) {
                $query->where('agent_id', $agent->id);
            }])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'customers' => $customers,
        ]);
    }
}

