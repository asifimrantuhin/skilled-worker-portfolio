<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CancellationPolicyController extends Controller
{
    public function index(Request $request)
    {
        $policies = CancellationPolicy::with('rules')
            ->withCount('packages')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'policies' => $policies,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_refundable' => 'boolean',
            'is_default' => 'boolean',
            'rules' => 'required|array|min:1',
            'rules.*.days_before_travel' => 'required|integer|min:0',
            'rules.*.refund_percentage' => 'required|numeric|min:0|max:100',
            'rules.*.fee_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // If setting as default, unset other defaults
            if ($request->is_default) {
                CancellationPolicy::where('is_default', true)->update(['is_default' => false]);
            }

            $policy = CancellationPolicy::create($request->only([
                'name', 'description', 'is_refundable', 'is_default'
            ]));

            // Create rules
            foreach ($request->rules as $ruleData) {
                $policy->rules()->create($ruleData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cancellation policy created successfully',
                'policy' => $policy->load('rules'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create policy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $policy = CancellationPolicy::with(['rules' => function ($query) {
            $query->orderBy('days_before_travel', 'desc');
        }])
            ->withCount('packages')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'policy' => $policy,
        ]);
    }

    public function update(Request $request, $id)
    {
        $policy = CancellationPolicy::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'description' => 'nullable|string',
            'is_refundable' => 'boolean',
            'is_default' => 'boolean',
            'rules' => 'sometimes|array|min:1',
            'rules.*.id' => 'nullable|exists:cancellation_policy_rules,id',
            'rules.*.days_before_travel' => 'required|integer|min:0',
            'rules.*.refund_percentage' => 'required|numeric|min:0|max:100',
            'rules.*.fee_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // If setting as default, unset other defaults
            if ($request->is_default && !$policy->is_default) {
                CancellationPolicy::where('is_default', true)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $policy->update($request->only([
                'name', 'description', 'is_refundable', 'is_default'
            ]));

            // Update rules if provided
            if ($request->has('rules')) {
                $existingRuleIds = $policy->rules->pluck('id')->toArray();
                $updatedRuleIds = [];

                foreach ($request->rules as $ruleData) {
                    if (isset($ruleData['id']) && in_array($ruleData['id'], $existingRuleIds)) {
                        // Update existing rule
                        CancellationPolicyRule::where('id', $ruleData['id'])->update([
                            'days_before_travel' => $ruleData['days_before_travel'],
                            'refund_percentage' => $ruleData['refund_percentage'],
                            'fee_amount' => $ruleData['fee_amount'] ?? null,
                        ]);
                        $updatedRuleIds[] = $ruleData['id'];
                    } else {
                        // Create new rule
                        $newRule = $policy->rules()->create($ruleData);
                        $updatedRuleIds[] = $newRule->id;
                    }
                }

                // Delete rules not in the updated list
                CancellationPolicyRule::where('cancellation_policy_id', $id)
                    ->whereNotIn('id', $updatedRuleIds)
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cancellation policy updated successfully',
                'policy' => $policy->fresh(['rules']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update policy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $policy = CancellationPolicy::withCount('packages')->findOrFail($id);

        if ($policy->packages_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a policy that is assigned to packages. Reassign packages first.',
            ], 400);
        }

        if ($policy->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the default policy. Set another policy as default first.',
            ], 400);
        }

        $policy->rules()->delete();
        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cancellation policy deleted successfully',
        ]);
    }

    public function setDefault($id)
    {
        DB::transaction(function () use ($id) {
            CancellationPolicy::where('is_default', true)->update(['is_default' => false]);
            CancellationPolicy::where('id', $id)->update(['is_default' => true]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Default policy updated',
        ]);
    }
}
