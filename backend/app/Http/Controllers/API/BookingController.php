<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\AgentCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Booking::with(['package', 'user', 'agent', 'payments']);

        // Filter by user role
        if ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            $query->where('agent_id', $user->id);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->where('travel_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('travel_date', '<=', $request->date_to);
        }

        $bookings = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'travel_date' => 'required|date|after:today',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'infants' => 'nullable|integer|min:0',
            'travelers_info' => 'required|array',
            'travelers_info.*.name' => 'required|string',
            'travelers_info.*.email' => 'required|email',
            'travelers_info.*.phone' => 'required|string',
            'travelers_info.*.passport' => 'nullable|string',
            'special_requests' => 'nullable|string',
            'agent_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $package = Package::findOrFail($request->package_id);

        // Check availability
        $totalParticipants = $request->adults + ($request->children ?? 0);
        $availability = PackageAvailability::where('package_id', $package->id)
            ->where('date', $request->travel_date)
            ->first();

        if ($availability) {
            $remainingSlots = $availability->available_slots - $availability->booked_slots;
            if ($remainingSlots < $totalParticipants) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough slots available',
                ], 400);
            }
        } elseif ($package->max_participants < $totalParticipants) {
            return response()->json([
                'success' => false,
                'message' => 'Exceeds maximum participants',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate pricing
            $packagePrice = $availability->price_override ?? $package->price;
            $totalPrice = $packagePrice * $totalParticipants;
            $discount = 0;
            $tax = $totalPrice * 0.1; // 10% tax
            $totalAmount = $totalPrice + $tax - $discount;

            // Create booking
            $booking = Booking::create([
                'package_id' => $package->id,
                'user_id' => $request->user()->id,
                'agent_id' => $request->agent_id ?? null,
                'travel_date' => $request->travel_date,
                'adults' => $request->adults,
                'children' => $request->children ?? 0,
                'infants' => $request->infants ?? 0,
                'package_price' => $packagePrice,
                'discount' => $discount,
                'tax' => $tax,
                'total_amount' => $totalAmount,
                'travelers_info' => $request->travelers_info,
                'special_requests' => $request->special_requests,
            ]);

            // Update availability
            if ($availability) {
                $availability->increment('booked_slots', $totalParticipants);
            }

            // Update package bookings count
            $package->increment('bookings_count');

            // Create agent commission if agent is assigned
            if ($request->agent_id) {
                $agent = \App\Models\User::find($request->agent_id);
                if ($agent && $agent->isAgent() && $agent->commission_rate) {
                    AgentCommission::create([
                        'agent_id' => $agent->id,
                        'booking_id' => $booking->id,
                        'booking_amount' => $totalAmount,
                        'commission_rate' => $agent->commission_rate,
                        'commission_amount' => ($totalAmount * $agent->commission_rate) / 100,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'booking' => $booking->load(['package', 'user', 'agent']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $booking = Booking::with(['package', 'user', 'agent', 'payments', 'commission'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'booking' => $booking,
        ]);
    }

    public function confirm(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        
        $booking->status = 'confirmed';
        $booking->confirmed_at = now();
        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Booking confirmed successfully',
            'booking' => $booking,
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking = Booking::findOrFail($id);
        
        // Check if user owns the booking
        if ($request->user()->isCustomer() && $booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $booking->status = 'cancelled';
            $booking->cancellation_reason = $request->cancellation_reason;
            $booking->cancelled_at = now();
            $booking->save();

            // Release availability slots
            $availability = PackageAvailability::where('package_id', $booking->package_id)
                ->where('date', $booking->travel_date)
                ->first();

            if ($availability) {
                $totalParticipants = $booking->adults + $booking->children;
                $availability->decrement('booked_slots', $totalParticipants);
            }

            // Cancel agent commission
            if ($booking->commission) {
                $booking->commission->status = 'cancelled';
                $booking->commission->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking = Booking::findOrFail($id);
        $booking->status = $request->status;
        
        if ($request->status === 'confirmed') {
            $booking->confirmed_at = now();
        } elseif ($request->status === 'cancelled') {
            $booking->cancelled_at = now();
        }

        $booking->save();

        return response()->json([
            'success' => true,
            'message' => 'Booking status updated successfully',
            'booking' => $booking,
        ]);
    }
}

