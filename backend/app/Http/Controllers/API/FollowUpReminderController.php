<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FollowUpReminder;
use App\Models\Inquiry;
use App\Models\Quote;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FollowUpReminderController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user();
        $query = FollowUpReminder::forAgent($agent->id)->with('remindable');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('reminder_type', $request->type);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->boolean('due_only')) {
            $query->due();
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        $reminders = $query->orderBy('remind_at', 'asc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'reminders' => $reminders,
        ]);
    }

    public function dashboard(Request $request)
    {
        $agent = $request->user();

        $dueNow = FollowUpReminder::forAgent($agent->id)->due()->count();
        $upcoming24h = FollowUpReminder::forAgent($agent->id)->upcoming(24)->count();
        $overdue = FollowUpReminder::forAgent($agent->id)->overdue()->count();

        $dueReminders = FollowUpReminder::forAgent($agent->id)
            ->due()
            ->with('remindable')
            ->orderBy('remind_at', 'asc')
            ->limit(10)
            ->get();

        $upcomingReminders = FollowUpReminder::forAgent($agent->id)
            ->upcoming(48)
            ->with('remindable')
            ->orderBy('remind_at', 'asc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => [
                'due_now' => $dueNow,
                'upcoming_24h' => $upcoming24h,
                'overdue' => $overdue,
            ],
            'due_reminders' => $dueReminders,
            'upcoming_reminders' => $upcomingReminders,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'nullable|string|max:100',
            'customer_email' => 'required|email',
            'customer_phone' => 'nullable|string|max:20',
            'customer_id' => 'nullable|exists:users,id',
            'reminder_type' => 'required|in:inquiry,quote,booking,post_trip,custom',
            'remindable_type' => 'nullable|in:inquiry,quote,booking',
            'remindable_id' => 'nullable|integer',
            'title' => 'required|string|max:200',
            'notes' => 'nullable|string',
            'remind_at' => 'required|date|after:now',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['agent_id'] = $request->user()->id;

        // Map remindable type to model class
        if (isset($data['remindable_type']) && isset($data['remindable_id'])) {
            $typeMap = [
                'inquiry' => Inquiry::class,
                'quote' => Quote::class,
                'booking' => Booking::class,
            ];
            $data['remindable_type'] = $typeMap[$data['remindable_type']] ?? null;
        }

        $reminder = FollowUpReminder::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Reminder created successfully',
            'reminder' => $reminder,
        ], 201);
    }

    public function show($id)
    {
        $reminder = FollowUpReminder::with(['remindable', 'customer'])
            ->findOrFail($id);

        // Check authorization
        if ($reminder->agent_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'reminder' => $reminder,
        ]);
    }

    public function update(Request $request, $id)
    {
        $reminder = FollowUpReminder::findOrFail($id);

        if ($reminder->agent_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:200',
            'notes' => 'nullable|string',
            'remind_at' => 'date|after:now',
            'priority' => 'in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reminder->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Reminder updated successfully',
            'reminder' => $reminder,
        ]);
    }

    public function complete($id)
    {
        $reminder = FollowUpReminder::findOrFail($id);

        if ($reminder->agent_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $reminder->complete();

        return response()->json([
            'success' => true,
            'message' => 'Reminder marked as complete',
            'reminder' => $reminder,
        ]);
    }

    public function snooze(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'hours' => 'nullable|integer|min:1|max:168', // Max 1 week
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $reminder = FollowUpReminder::findOrFail($id);

        if ($reminder->agent_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $hours = $request->hours ?? 1;
        $reminder->snooze($hours);

        return response()->json([
            'success' => true,
            'message' => "Reminder snoozed for {$hours} hour(s)",
            'reminder' => $reminder,
        ]);
    }

    public function reschedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'remind_at' => 'required|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $reminder = FollowUpReminder::findOrFail($id);

        if ($reminder->agent_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $reminder->reschedule(new \DateTime($request->remind_at));

        return response()->json([
            'success' => true,
            'message' => 'Reminder rescheduled',
            'reminder' => $reminder,
        ]);
    }

    public function cancel($id)
    {
        $reminder = FollowUpReminder::findOrFail($id);

        if ($reminder->agent_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $reminder->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Reminder cancelled',
        ]);
    }

    public function bulkComplete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:follow_up_reminders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = FollowUpReminder::whereIn('id', $request->ids)
            ->where('agent_id', $request->user()->id)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} reminder(s) marked as complete",
        ]);
    }
}
