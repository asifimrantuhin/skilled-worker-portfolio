<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AgentCommission;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\InventoryHold;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Booking::with(['package', 'user', 'agent', 'payments', 'promoCode']);

        if ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            $query->where('agent_id', $user->id);
        }

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

    /**
     * Hold inventory slots before checkout
     */
    public function holdInventory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'travel_date' => 'required|date|after:today',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $package = Package::findOrFail($request->package_id);
        $totalSlots = $request->adults + ($request->children ?? 0);
        $availableSlots = $package->getAvailableSlots($request->travel_date);

        if ($availableSlots < $totalSlots) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough slots available',
                'available_slots' => $availableSlots,
            ], 400);
        }

        // Release any existing hold for this user on same package/date
        InventoryHold::where('user_id', $request->user()->id)
            ->where('package_id', $request->package_id)
            ->where('travel_date', $request->travel_date)
            ->where('status', 'active')
            ->update(['status' => 'released']);

        $hold = InventoryHold::create([
            'package_id' => $request->package_id,
            'user_id' => $request->user()->id,
            'travel_date' => $request->travel_date,
            'slots_held' => $totalSlots,
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory held for 15 minutes',
            'hold_token' => $hold->hold_token,
            'expires_at' => $hold->expires_at,
        ]);
    }

    /**
     * Validate and preview promo code discount
     */
    public function validatePromoCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $promo = PromoCode::where('code', strtoupper($request->code))->first();

        if (! $promo) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid promo code',
            ], 404);
        }

        if (! $promo->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code is expired or inactive',
            ], 400);
        }

        if (! $promo->isApplicableToPackage($request->package_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not applicable to this package',
            ], 400);
        }

        if (! $promo->canBeUsedByUser($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the usage limit for this promo code',
            ], 400);
        }

        $discount = $promo->calculateDiscount($request->amount);

        if ($discount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum order amount not met',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'promo_code' => [
                'id' => $promo->id,
                'code' => $promo->code,
                'name' => $promo->name,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
            ],
            'discount_amount' => $discount,
            'final_amount' => max(0, $request->amount - $discount),
        ]);
    }

    /**
     * Preview cancellation refund
     */
    public function previewCancellation($id)
    {
        $booking = Booking::with('package.cancellationPolicy')->findOrFail($id);

        if ($booking->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled',
            ], 400);
        }

        $refundInfo = $booking->calculateCancellationRefund();
        $policy = $booking->getCancellationPolicy();

        return response()->json([
            'success' => true,
            'booking_id' => $booking->id,
            'paid_amount' => $booking->paid_amount,
            'days_until_travel' => $booking->getDaysUntilTravel(),
            'policy_name' => $policy?->name,
            'refund_percentage' => $refundInfo['refund_percentage'],
            'refund_amount' => $refundInfo['refund_amount'],
            'cancellation_fee' => $refundInfo['cancellation_fee'],
            'policy_rules' => $policy?->rules,
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
            'hold_token' => 'nullable|string',
            'promo_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $package = Package::findOrFail($request->package_id);
        $totalParticipants = $request->adults + ($request->children ?? 0);

        // Check for valid inventory hold
        $hold = null;
        if ($request->hold_token) {
            $hold = InventoryHold::where('hold_token', $request->hold_token)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first();

            if (! $hold || $hold->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inventory hold expired. Please start over.',
                ], 400);
            }
        }

        // Check availability (accounting for holds)
        $availableSlots = $package->getAvailableSlots($request->travel_date);
        $slotsNeeded = $hold ? 0 : $totalParticipants; // If hold exists, slots already reserved

        if ($availableSlots < $slotsNeeded) {
            return response()->json([
                'success' => false,
                'message' => 'Not enough slots available',
                'available_slots' => $availableSlots,
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate pricing
            $availability = PackageAvailability::where('package_id', $package->id)
                ->where('date', $request->travel_date)
                ->first();

            $packagePrice = $availability->price_override ?? $package->price;
            $subtotal = $packagePrice * $totalParticipants;

            // Apply promo code
            $promoDiscount = 0;
            $promoCodeId = null;

            if ($request->promo_code) {
                $promo = PromoCode::where('code', strtoupper($request->promo_code))->first();

                if ($promo && $promo->isValid() &&
                    $promo->isApplicableToPackage($package->id) &&
                    $promo->canBeUsedByUser($request->user()->id)) {

                    $promoDiscount = $promo->calculateDiscount($subtotal);
                    $promoCodeId = $promo->id;
                }
            }

            $discount = $promoDiscount;
            $tax = ($subtotal - $discount) * 0.1; // 10% tax
            $totalAmount = $subtotal + $tax - $discount;

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
                'promo_code_id' => $promoCodeId,
                'promo_discount' => $promoDiscount,
                'tax' => $tax,
                'total_amount' => $totalAmount,
                'travelers_info' => $request->travelers_info,
                'special_requests' => $request->special_requests,
                'hold_token' => $request->hold_token,
            ]);

            // Record promo usage
            if ($promoCodeId) {
                PromoCodeUsage::create([
                    'promo_code_id' => $promoCodeId,
                    'user_id' => $request->user()->id,
                    'booking_id' => $booking->id,
                    'discount_applied' => $promoDiscount,
                ]);

                PromoCode::where('id', $promoCodeId)->increment('usage_count');
            }

            // Convert hold or update availability
            if ($hold) {
                $hold->markAsConverted($booking->id);
            }

            // Update availability
            if ($availability) {
                $availability->increment('booked_slots', $totalParticipants);
            }

            // Update package bookings count
            $package->increment('bookings_count');

            // Create agent commission
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
                'booking' => $booking->load(['package', 'user', 'agent', 'promoCode']),
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
        $booking = Booking::with(['package.cancellationPolicy', 'user', 'agent', 'payments', 'commission', 'promoCode'])
            ->findOrFail($id);

        $cancellationPreview = null;
        if (in_array($booking->status, ['pending', 'confirmed'])) {
            $cancellationPreview = $booking->calculateCancellationRefund();
        }

        return response()->json([
            'success' => true,
            'booking' => $booking,
            'cancellation_preview' => $cancellationPreview,
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $booking = Booking::with('package.cancellationPolicy')->findOrFail($id);

        // Check authorization
        if ($request->user()->isCustomer() && $booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Booking is already cancelled',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Calculate refund based on cancellation policy
            $refundInfo = $booking->calculateCancellationRefund();

            $booking->status = 'cancelled';
            $booking->cancellation_reason = $request->cancellation_reason;
            $booking->cancelled_at = now();
            $booking->cancellation_fee = $refundInfo['cancellation_fee'];
            $booking->refund_amount = $refundInfo['refund_amount'];
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
                'refund_amount' => $refundInfo['refund_amount'],
                'cancellation_fee' => $refundInfo['cancellation_fee'],
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
                'errors' => $validator->errors(),
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

    /**
     * Release an inventory hold
     */
    public function releaseHold(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hold_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $hold = InventoryHold::where('hold_token', $request->hold_token)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $hold) {
            return response()->json([
                'success' => false,
                'message' => 'Hold not found or already released',
            ], 404);
        }

        $hold->release();

        return response()->json([
            'success' => true,
            'message' => 'Hold released successfully',
        ]);
    }
}

