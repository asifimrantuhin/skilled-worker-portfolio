<?php

namespace App\Http\Controllers;

use App\Models\EscalationRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EscalationRuleController extends Controller
{
    /**
     * Get all escalation rules
     */
    public function index(Request $request): JsonResponse
    {
        $query = EscalationRule::query();

        // Filter by trigger type
        if ($request->has('trigger_type')) {
            $query->byTriggerType($request->trigger_type);
        }

        // Filter by active status
        if ($request->has('active')) {
            if ($request->boolean('active')) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        // Filter by escalation level
        if ($request->has('escalation_level')) {
            $query->where('escalation_level', $request->escalation_level);
        }

        $rules = $query->ordered()->paginate($request->get('per_page', 20));

        return response()->json($rules);
    }

    /**
     * Create a new escalation rule
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|string|in:time_based,condition_based,manual',
            'conditions' => 'required|array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string|in:equals,not_equals,in,not_in,greater_than,less_than,is_null,is_not_null',
            'conditions.*.value' => 'nullable',
            'actions' => 'required|array',
            'actions.*.type' => 'required|string|in:assign_to,change_priority,add_tag,notify,escalate',
            'escalation_level' => 'required|integer|min:1|max:5',
            'time_threshold_hours' => 'nullable|numeric|min:0',
            'notify_customer' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rule = EscalationRule::create($validator->validated());

        return response()->json([
            'message' => 'Escalation rule created successfully',
            'escalation_rule' => $rule,
        ], 201);
    }

    /**
     * Get a specific escalation rule
     */
    public function show(EscalationRule $escalationRule): JsonResponse
    {
        return response()->json(['escalation_rule' => $escalationRule]);
    }

    /**
     * Update an escalation rule
     */
    public function update(Request $request, EscalationRule $escalationRule): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'string|in:time_based,condition_based,manual',
            'conditions' => 'array',
            'conditions.*.field' => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string|in:equals,not_equals,in,not_in,greater_than,less_than,is_null,is_not_null',
            'conditions.*.value' => 'nullable',
            'actions' => 'array',
            'actions.*.type' => 'required_with:actions|string|in:assign_to,change_priority,add_tag,notify,escalate',
            'escalation_level' => 'integer|min:1|max:5',
            'time_threshold_hours' => 'nullable|numeric|min:0',
            'notify_customer' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $escalationRule->update($validator->validated());

        return response()->json([
            'message' => 'Escalation rule updated successfully',
            'escalation_rule' => $escalationRule,
        ]);
    }

    /**
     * Delete an escalation rule
     */
    public function destroy(EscalationRule $escalationRule): JsonResponse
    {
        $escalationRule->delete();

        return response()->json(['message' => 'Escalation rule deleted successfully']);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(EscalationRule $escalationRule): JsonResponse
    {
        $escalationRule->update(['is_active' => !$escalationRule->is_active]);

        return response()->json([
            'message' => $escalationRule->is_active ? 'Escalation rule activated' : 'Escalation rule deactivated',
            'escalation_rule' => $escalationRule,
        ]);
    }

    /**
     * Reorder rules
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rules' => 'required|array',
            'rules.*.id' => 'required|integer|exists:escalation_rules,id',
            'rules.*.priority' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->rules as $ruleData) {
            EscalationRule::where('id', $ruleData['id'])->update(['priority' => $ruleData['priority']]);
        }

        return response()->json(['message' => 'Rules reordered successfully']);
    }

    /**
     * Test rule against a ticket
     */
    public function testRule(Request $request, EscalationRule $escalationRule): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|integer|exists:tickets,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = \App\Models\Ticket::find($request->ticket_id);
        $matches = $escalationRule->matchesTicket($ticket);

        return response()->json([
            'rule' => $escalationRule->name,
            'ticket_id' => $ticket->id,
            'matches' => $matches,
            'conditions' => $escalationRule->conditions,
        ]);
    }

    /**
     * Get available trigger types and actions
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'trigger_types' => [
                'time_based' => 'Triggered after a certain time threshold',
                'condition_based' => 'Triggered when conditions are met',
                'manual' => 'Triggered manually by agents',
            ],
            'condition_operators' => [
                'equals' => 'Equals',
                'not_equals' => 'Not equals',
                'in' => 'In list',
                'not_in' => 'Not in list',
                'greater_than' => 'Greater than',
                'less_than' => 'Less than',
                'is_null' => 'Is empty',
                'is_not_null' => 'Is not empty',
            ],
            'action_types' => [
                'assign_to' => 'Assign to user',
                'change_priority' => 'Change priority',
                'add_tag' => 'Add tag',
                'notify' => 'Send notification',
                'escalate' => 'Escalate ticket',
            ],
            'condition_fields' => [
                'status' => 'Ticket status',
                'priority' => 'Ticket priority',
                'escalation_level' => 'Current escalation level',
                'assigned_to' => 'Assigned agent',
                'sla_breached' => 'SLA breached',
            ],
        ]);
    }
}
