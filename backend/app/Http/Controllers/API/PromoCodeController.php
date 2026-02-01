<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = PromoCode::withCount('usages');

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('valid_until')
                            ->orWhere('valid_until', '>=', now());
                    });
            } elseif ($request->status === 'expired') {
                $query->where('valid_until', '<', now());
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                    ->orWhere('name', 'like', "%{$request->search}%");
            });
        }

        $promoCodes = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'promo_codes' => $promoCodes,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:promo_codes,code|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'applicable_packages' => 'nullable|array',
            'applicable_packages.*' => 'exists:packages,id',
            'excluded_packages' => 'nullable|array',
            'excluded_packages.*' => 'exists:packages,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['code'] = strtoupper($data['code']);

        $promoCode = PromoCode::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Promo code created successfully',
            'promo_code' => $promoCode,
        ], 201);
    }

    public function show($id)
    {
        $promoCode = PromoCode::withCount('usages')
            ->with(['usages' => function ($query) {
                $query->with(['user:id,name,email', 'booking:id,travel_date,total_amount'])
                    ->orderBy('created_at', 'desc')
                    ->limit(50);
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'promo_code' => $promoCode,
        ]);
    }

    public function update(Request $request, $id)
    {
        $promoCode = PromoCode::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'string|max:50|unique:promo_codes,code,' . $id,
            'name' => 'string|max:100',
            'description' => 'nullable|string',
            'discount_type' => 'in:percentage,fixed',
            'discount_value' => 'numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'applicable_packages' => 'nullable|array',
            'applicable_packages.*' => 'exists:packages,id',
            'excluded_packages' => 'nullable|array',
            'excluded_packages.*' => 'exists:packages,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $promoCode->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Promo code updated successfully',
            'promo_code' => $promoCode,
        ]);
    }

    public function destroy($id)
    {
        $promoCode = PromoCode::findOrFail($id);

        // Check if promo has been used
        if ($promoCode->usage_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a promo code that has been used. Deactivate it instead.',
            ], 400);
        }

        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promo code deleted successfully',
        ]);
    }

    public function toggleStatus($id)
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->is_active = !$promoCode->is_active;
        $promoCode->save();

        return response()->json([
            'success' => true,
            'message' => $promoCode->is_active ? 'Promo code activated' : 'Promo code deactivated',
            'promo_code' => $promoCode,
        ]);
    }

    public function usageReport($id)
    {
        $promoCode = PromoCode::findOrFail($id);

        $usages = PromoCodeUsage::where('promo_code_id', $id)
            ->with(['user:id,name,email', 'booking:id,travel_date,total_amount,status'])
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        $stats = [
            'total_usage' => $promoCode->usage_count,
            'total_discount_given' => PromoCodeUsage::where('promo_code_id', $id)->sum('discount_applied'),
            'unique_users' => PromoCodeUsage::where('promo_code_id', $id)->distinct('user_id')->count('user_id'),
        ];

        return response()->json([
            'success' => true,
            'promo_code' => $promoCode->only(['id', 'code', 'name', 'discount_type', 'discount_value']),
            'stats' => $stats,
            'usages' => $usages,
        ]);
    }
}
