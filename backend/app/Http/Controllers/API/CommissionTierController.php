<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AgentTierHistory;
use App\Models\CommissionTier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionTierController extends Controller
{
    public function index(Request $request)
    {
        $tiers = CommissionTier::ordered()
            ->withCount('agents')
            ->get();

        return response()->json([
            'success' => true,
            'tiers' => $tiers,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'min_bookings' => 'required|numeric|min:0',
            'min_revenue' => 'required|numeric|min:0',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'bonus_rate' => 'nullable|numeric|min:0|max:100',
            'benefits' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tier = CommissionTier::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Commission tier created successfully',
            'tier' => $tier,
        ], 201);
    }

    public function show($id)
    {
        $tier = CommissionTier::withCount('agents')
            ->with(['agents' => function ($query) {
                $query->select('id', 'name', 'email', 'commission_tier_id')
                    ->limit(50);
            }])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'tier' => $tier,
        ]);
    }

    public function update(Request $request, $id)
    {
        $tier = CommissionTier::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:100',
            'description' => 'nullable|string',
            'min_bookings' => 'numeric|min:0',
            'min_revenue' => 'numeric|min:0',
            'commission_rate' => 'numeric|min:0|max:100',
            'bonus_rate' => 'nullable|numeric|min:0|max:100',
            'benefits' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tier->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Commission tier updated successfully',
            'tier' => $tier,
        ]);
    }

    public function destroy($id)
    {
        $tier = CommissionTier::withCount('agents')->findOrFail($id);

        if ($tier->agents_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete tier with assigned agents. Reassign agents first.',
            ], 400);
        }

        $tier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commission tier deleted successfully',
        ]);
    }

    /**
     * Assign agent to tier
     */
    public function assignAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:users,id',
            'tier_id' => 'required|exists:commission_tiers,id',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $agent = User::findOrFail($request->agent_id);

        if (!$agent->isAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent',
            ], 400);
        }

        $tier = CommissionTier::findOrFail($request->tier_id);

        // Record tier change history
        AgentTierHistory::recordTierChange($agent->id, $tier->id, $request->reason);

        // Update agent
        $agent->commission_tier_id = $tier->id;
        $agent->commission_rate = $tier->getTotalCommissionRate();
        $agent->save();

        return response()->json([
            'success' => true,
            'message' => "Agent assigned to {$tier->name} tier",
            'agent' => $agent,
        ]);
    }

    /**
     * Get agent's tier history
     */
    public function agentHistory($agentId)
    {
        $history = AgentTierHistory::forAgent($agentId)
            ->with('tier')
            ->orderBy('effective_from', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Auto-evaluate and promote/demote agents based on performance
     */
    public function evaluateAgents(Request $request)
    {
        $agents = User::where('role', 'agent')
            ->where('is_active', true)
            ->get();

        $changes = [];

        foreach ($agents as $agent) {
            $recommendedTier = CommissionTier::getTierForAgent($agent->id);

            if (!$recommendedTier) {
                continue;
            }

            if ($agent->commission_tier_id !== $recommendedTier->id) {
                $oldTier = $agent->commissionTier;

                AgentTierHistory::recordTierChange(
                    $agent->id,
                    $recommendedTier->id,
                    'Automatic evaluation based on monthly performance'
                );

                $agent->commission_tier_id = $recommendedTier->id;
                $agent->commission_rate = $recommendedTier->getTotalCommissionRate();
                $agent->save();

                $changes[] = [
                    'agent' => $agent->name,
                    'from' => $oldTier?->name ?? 'None',
                    'to' => $recommendedTier->name,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($changes) . ' agent tier(s) updated',
            'changes' => $changes,
        ]);
    }
}
