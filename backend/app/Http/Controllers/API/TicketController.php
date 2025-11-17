<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::with(['user', 'assignedAgent', 'inquiry', 'booking']);

        // Filter by user role
        if ($user->isCustomer()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            $query->where('assigned_to', $user->id);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inquiry_id' => 'nullable|exists:inquiries,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::create([
            'inquiry_id' => $request->inquiry_id,
            'booking_id' => $request->booking_id,
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority ?? 'medium',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'ticket' => $ticket->load(['user', 'inquiry', 'booking']),
        ], 201);
    }

    public function show($id)
    {
        $ticket = Ticket::with(['user', 'assignedAgent', 'inquiry', 'booking', 'replies.user'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    public function update(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'subject' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket->update($request->only(['subject', 'description', 'priority']));

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'ticket' => $ticket,
        ]);
    }

    public function addReply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'is_internal' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::findOrFail($id);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $request->message,
            'is_internal' => $request->is_internal ?? false,
        ]);

        // Update ticket status if customer replies
        if ($request->user()->isCustomer() && $ticket->status === 'resolved') {
            $ticket->status = 'open';
            $ticket->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully',
            'reply' => $reply->load('user'),
        ], 201);
    }

    public function assign(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $agent = \App\Models\User::find($request->agent_id);
        if (!$agent->isAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not an agent',
            ], 422);
        }

        $ticket = Ticket::findOrFail($id);
        $ticket->assigned_to = $request->agent_id;
        $ticket->status = 'in_progress';
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully',
            'ticket' => $ticket,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::findOrFail($id);
        $ticket->status = $request->status;
        
        if ($request->status === 'resolved') {
            $ticket->resolved_at = now();
        }

        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
            'ticket' => $ticket,
        ]);
    }

    public function resolve(Request $request, $id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->status = 'resolved';
        $ticket->resolved_at = now();
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket resolved successfully',
            'ticket' => $ticket,
        ]);
    }
}

