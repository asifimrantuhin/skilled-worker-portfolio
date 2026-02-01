<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FollowUpReminder;
use App\Models\Package;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user();
        $query = Quote::forAgent($agent->id)->with('customer');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('quote_number', 'like', "%{$request->search}%")
                    ->orWhere('customer_name', 'like', "%{$request->search}%")
                    ->orWhere('customer_email', 'like', "%{$request->search}%")
                    ->orWhere('title', 'like', "%{$request->search}%");
            });
        }

        $quotes = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'quotes' => $quotes,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:100',
            'customer_email' => 'required|email',
            'customer_phone' => 'nullable|string|max:20',
            'customer_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:package,custom',
            'items.*.package_id' => 'required_if:items.*.type,package|exists:packages,id',
            'items.*.name' => 'required|string',
            'items.*.description' => 'nullable|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.travel_date' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'valid_days' => 'nullable|integer|min:1|max:90',
            'terms_conditions' => 'nullable|string',
            'internal_notes' => 'nullable|string',
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
            // Process items and calculate totals
            $items = [];
            $subtotal = 0;

            foreach ($request->items as $item) {
                $processedItem = [
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['price'] * $item['quantity'],
                ];

                if ($item['type'] === 'package' && isset($item['package_id'])) {
                    $package = Package::find($item['package_id']);
                    $processedItem['package_id'] = $package->id;
                    $processedItem['package_details'] = [
                        'destination' => $package->destination,
                        'duration' => $package->duration,
                    ];
                }

                if (isset($item['travel_date'])) {
                    $processedItem['travel_date'] = $item['travel_date'];
                }

                $items[] = $processedItem;
                $subtotal += $processedItem['total'];
            }

            $discount = $request->discount ?? 0;
            $taxRate = $request->tax_rate ?? 10;
            $tax = ($subtotal - $discount) * ($taxRate / 100);
            $total = $subtotal - $discount + $tax;

            $quote = Quote::create([
                'agent_id' => $request->user()->id,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'title' => $request->title,
                'description' => $request->description,
                'items' => $items,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'valid_until' => now()->addDays($request->valid_days ?? 14),
                'terms_conditions' => $request->terms_conditions,
                'internal_notes' => $request->internal_notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote created successfully',
                'quote' => $quote,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create quote',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $quote = Quote::with(['agent', 'customer', 'convertedBooking', 'reminders'])
            ->findOrFail($id);

        // Check authorization
        if (request()->user()->isAgent() && $quote->agent_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'quote' => $quote,
        ]);
    }

    public function update(Request $request, $id)
    {
        $quote = Quote::findOrFail($id);

        // Only allow editing draft quotes
        if (!in_array($quote->status, ['draft'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a quote that has been sent',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'string|max:100',
            'customer_email' => 'email',
            'customer_phone' => 'nullable|string|max:20',
            'title' => 'string|max:200',
            'description' => 'nullable|string',
            'items' => 'array|min:1',
            'items.*.type' => 'required_with:items|in:package,custom',
            'items.*.name' => 'required_with:items|string',
            'items.*.price' => 'required_with:items|numeric|min:0',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'discount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'valid_until' => 'nullable|date|after:today',
            'terms_conditions' => 'nullable|string',
            'internal_notes' => 'nullable|string',
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
            $data = $request->only([
                'customer_name', 'customer_email', 'customer_phone',
                'title', 'description', 'terms_conditions', 'internal_notes', 'valid_until'
            ]);

            // Recalculate if items changed
            if ($request->has('items')) {
                $items = [];
                $subtotal = 0;

                foreach ($request->items as $item) {
                    $processedItem = [
                        'type' => $item['type'],
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'total' => $item['price'] * $item['quantity'],
                    ];

                    if (isset($item['package_id'])) {
                        $processedItem['package_id'] = $item['package_id'];
                    }
                    if (isset($item['travel_date'])) {
                        $processedItem['travel_date'] = $item['travel_date'];
                    }

                    $items[] = $processedItem;
                    $subtotal += $processedItem['total'];
                }

                $discount = $request->discount ?? $quote->discount;
                $taxRate = $request->tax_rate ?? 10;
                $tax = ($subtotal - $discount) * ($taxRate / 100);

                $data['items'] = $items;
                $data['subtotal'] = $subtotal;
                $data['discount'] = $discount;
                $data['tax'] = $tax;
                $data['total'] = $subtotal - $discount + $tax;
            }

            $quote->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quote updated successfully',
                'quote' => $quote->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update quote',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $quote = Quote::findOrFail($id);

        if (!in_array($quote->status, ['draft', 'expired', 'declined'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an active quote',
            ], 400);
        }

        $quote->delete();

        return response()->json([
            'success' => true,
            'message' => 'Quote deleted successfully',
        ]);
    }

    public function send($id)
    {
        $quote = Quote::findOrFail($id);

        if ($quote->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Quote has already been sent',
            ], 400);
        }

        // In a real app, you'd send an email here
        // Mail::to($quote->customer_email)->send(new QuoteMail($quote));

        $quote->markAsSent();

        // Create follow-up reminder
        FollowUpReminder::create([
            'agent_id' => $quote->agent_id,
            'customer_email' => $quote->customer_email,
            'customer_name' => $quote->customer_name,
            'reminder_type' => 'quote',
            'remindable_type' => Quote::class,
            'remindable_id' => $quote->id,
            'title' => "Follow up on quote: {$quote->quote_number}",
            'remind_at' => now()->addDays(3),
            'priority' => 'medium',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quote sent successfully',
            'quote' => $quote,
        ]);
    }

    public function duplicate($id)
    {
        $original = Quote::findOrFail($id);

        $newQuote = $original->replicate();
        $newQuote->quote_number = null; // Will be auto-generated
        $newQuote->status = 'draft';
        $newQuote->sent_at = null;
        $newQuote->viewed_at = null;
        $newQuote->responded_at = null;
        $newQuote->converted_booking_id = null;
        $newQuote->valid_until = now()->addDays(14);
        $newQuote->save();

        return response()->json([
            'success' => true,
            'message' => 'Quote duplicated successfully',
            'quote' => $newQuote,
        ]);
    }

    /**
     * Public endpoint for customer to view quote
     */
    public function viewQuote(Request $request, $quoteNumber)
    {
        $quote = Quote::where('quote_number', $quoteNumber)->firstOrFail();

        if ($quote->status === 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Quote not found',
            ], 404);
        }

        $quote->markAsViewed();

        return response()->json([
            'success' => true,
            'quote' => $quote->only([
                'quote_number', 'title', 'description', 'items',
                'subtotal', 'discount', 'tax', 'total',
                'valid_until', 'terms_conditions', 'status'
            ]),
        ]);
    }

    /**
     * Public endpoint for customer to respond to quote
     */
    public function respondToQuote(Request $request, $quoteNumber)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:accept,decline',
            'message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $quote = Quote::where('quote_number', $quoteNumber)->firstOrFail();

        if (!in_array($quote->status, ['sent', 'viewed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Quote cannot be responded to',
            ], 400);
        }

        if ($quote->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Quote has expired',
            ], 400);
        }

        if ($request->action === 'accept') {
            $quote->accept();
            $message = 'Quote accepted successfully';
        } else {
            $quote->decline();
            $message = 'Quote declined';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
