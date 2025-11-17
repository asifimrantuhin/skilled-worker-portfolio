<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Inquiry::with(['user', 'package', 'assignedAgent']);

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

        $inquiries = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'inquiries' => $inquiries,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'nullable|exists:packages,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $inquiry = Inquiry::create([
            'user_id' => $request->user()->id ?? null,
            'package_id' => $request->package_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'subject' => $request->subject,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inquiry submitted successfully',
            'inquiry' => $inquiry,
        ], 201);
    }

    public function show($id)
    {
        $inquiry = Inquiry::with(['user', 'package', 'assignedAgent', 'ticket'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'inquiry' => $inquiry,
        ]);
    }

    public function update(Request $request, $id)
    {
        $inquiry = Inquiry::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'subject' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $inquiry->update($request->only(['subject', 'message']));

        return response()->json([
            'success' => true,
            'message' => 'Inquiry updated successfully',
            'inquiry' => $inquiry,
        ]);
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

        $inquiry = Inquiry::findOrFail($id);
        $inquiry->assigned_to = $request->agent_id;
        $inquiry->status = 'in_progress';
        $inquiry->save();

        return response()->json([
            'success' => true,
            'message' => 'Inquiry assigned successfully',
            'inquiry' => $inquiry,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:new,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $inquiry = Inquiry::findOrFail($id);
        $inquiry->status = $request->status;
        $inquiry->save();

        return response()->json([
            'success' => true,
            'message' => 'Inquiry status updated successfully',
            'inquiry' => $inquiry,
        ]);
    }
}

