<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReferralController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Referral::forReferrer($user->id)->with('referred');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $referrals = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        $stats = [
            'total_referrals' => Referral::forReferrer($user->id)->count(),
            'pending' => Referral::forReferrer($user->id)->where('status', 'pending')->count(),
            'registered' => Referral::forReferrer($user->id)->where('status', 'registered')->count(),
            'booked' => Referral::forReferrer($user->id)->where('status', 'booked')->count(),
            'rewarded' => Referral::forReferrer($user->id)->where('status', 'rewarded')->count(),
            'total_rewards' => Referral::forReferrer($user->id)->where('reward_paid', true)->sum('reward_amount'),
        ];

        return response()->json([
            'success' => true,
            'referrals' => $referrals,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if email is already a user
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered',
            ], 400);
        }

        // Check for existing active referral
        $existing = Referral::where('referred_email', $request->email)
            ->active()
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A referral already exists for this email',
            ], 400);
        }

        $referral = Referral::generateForUser(
            $request->user()->id,
            $request->email,
            $request->name
        );

        // In a real app, you'd send an email invitation here
        // Mail::to($request->email)->send(new ReferralInvitation($referral));

        return response()->json([
            'success' => true,
            'message' => 'Referral created successfully',
            'referral' => $referral,
        ], 201);
    }

    public function getMyReferralCode(Request $request)
    {
        $user = $request->user();

        if (!$user->referral_code) {
            $user->referral_code = 'AG-' . strtoupper(\Illuminate\Support\Str::random(8));
            $user->save();
        }

        return response()->json([
            'success' => true,
            'referral_code' => $user->referral_code,
            'referral_link' => config('app.frontend_url') . '/register?ref=' . $user->referral_code,
        ]);
    }

    /**
     * Validate referral code (public endpoint)
     */
    public function validateCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if it's a user's referral code
        $referrer = User::where('referral_code', strtoupper($request->code))->first();

        if ($referrer) {
            return response()->json([
                'success' => true,
                'valid' => true,
                'referrer_name' => $referrer->name,
                'type' => 'agent_code',
            ]);
        }

        // Check if it's a specific referral invitation
        $referral = Referral::findByCode($request->code);

        if ($referral) {
            return response()->json([
                'success' => true,
                'valid' => true,
                'referrer_name' => $referral->referrer->name ?? 'Unknown',
                'type' => 'invitation',
            ]);
        }

        return response()->json([
            'success' => false,
            'valid' => false,
            'message' => 'Invalid or expired referral code',
        ]);
    }

    public function show($id)
    {
        $referral = Referral::with(['referrer', 'referred'])->findOrFail($id);

        if ($referral->referrer_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'referral' => $referral,
        ]);
    }

    public function resendInvitation($id)
    {
        $referral = Referral::findOrFail($id);

        if ($referral->referrer_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($referral->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot resend invitation for this referral',
            ], 400);
        }

        // Extend expiry
        $referral->expires_at = now()->addDays(30);
        $referral->save();

        // In a real app, resend email
        // Mail::to($referral->referred_email)->send(new ReferralInvitation($referral));

        return response()->json([
            'success' => true,
            'message' => 'Invitation resent successfully',
        ]);
    }

    public function cancel($id)
    {
        $referral = Referral::findOrFail($id);

        if ($referral->referrer_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!in_array($referral->status, ['pending'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel this referral',
            ], 400);
        }

        $referral->markAsExpired();

        return response()->json([
            'success' => true,
            'message' => 'Referral cancelled',
        ]);
    }

    /**
     * Admin endpoint to process referral rewards
     */
    public function processRewards(Request $request)
    {
        $rewardable = Referral::rewardable()->get();

        $processed = 0;
        $rewardAmount = config('services.referral.reward_amount', 50);

        foreach ($rewardable as $referral) {
            $referral->markAsRewarded($rewardAmount);
            $processed++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$processed} referral reward(s) processed",
        ]);
    }
}
