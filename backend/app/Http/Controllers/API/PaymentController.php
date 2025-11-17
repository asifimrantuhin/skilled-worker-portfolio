<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function stripePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking = Booking::findOrFail($request->booking_id);

        // Verify booking ownership
        if ($request->user()->isCustomer() && $booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Here you would integrate with Stripe
            // For now, we'll simulate the payment
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));

            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $request->amount * 100, // Convert to cents
                'currency' => 'usd',
                'payment_method' => $request->payment_method_id,
                'confirm' => true,
                'return_url' => env('FRONTEND_URL') . '/payment/success',
            ]);

            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payment_method' => 'stripe',
                'amount' => $request->amount,
                'status' => $paymentIntent->status === 'succeeded' ? 'completed' : 'processing',
                'gateway_transaction_id' => $paymentIntent->id,
                'payment_data' => $paymentIntent->toArray(),
                'paid_at' => $paymentIntent->status === 'succeeded' ? now() : null,
            ]);

            // Update booking payment status
            $booking->paid_amount += $request->amount;
            if ($booking->paid_amount >= $booking->total_amount) {
                $booking->payment_status = 'paid';
            } else {
                $booking->payment_status = 'partial';
            }
            $booking->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment' => $payment,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sslcommerzPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking = Booking::findOrFail($request->booking_id);

        // Verify booking ownership
        if ($request->user()->isCustomer() && $booking->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payment_method' => 'sslcommerz',
                'amount' => $request->amount,
                'status' => 'pending',
            ]);

            // Here you would integrate with SSLCommerz
            // Initialize SSLCommerz payment gateway
            $sslcommerz = new \App\Services\SSLCommerzService();
            $paymentUrl = $sslcommerz->initiatePayment([
                'transaction_id' => $payment->transaction_id,
                'amount' => $request->amount,
                'currency' => 'BDT',
                'success_url' => env('FRONTEND_URL') . '/payment/success',
                'fail_url' => env('FRONTEND_URL') . '/payment/fail',
                'cancel_url' => env('FRONTEND_URL') . '/payment/cancel',
                'customer_name' => $booking->user->name,
                'customer_email' => $booking->user->email,
                'customer_phone' => $booking->user->phone,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated',
                'payment' => $payment,
                'payment_url' => $paymentUrl,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyPayment(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);

        // Verify with payment gateway
        if ($payment->payment_method === 'stripe') {
            // Verify with Stripe
            $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));
            $paymentIntent = $stripe->paymentIntents->retrieve($payment->gateway_transaction_id);
            
            $payment->status = $paymentIntent->status === 'succeeded' ? 'completed' : 'failed';
            $payment->payment_data = $paymentIntent->toArray();
            $payment->paid_at = $paymentIntent->status === 'succeeded' ? now() : null;
            $payment->save();
        } elseif ($payment->payment_method === 'sslcommerz') {
            // Verify with SSLCommerz
            $sslcommerz = new \App\Services\SSLCommerzService();
            $verification = $sslcommerz->verifyPayment($payment->gateway_transaction_id);
            
            $payment->status = $verification['status'] === 'VALID' ? 'completed' : 'failed';
            $payment->payment_data = $verification;
            $payment->paid_at = $verification['status'] === 'VALID' ? now() : null;
            $payment->save();
        }

        // Update booking payment status
        if ($payment->status === 'completed') {
            $booking = $payment->booking;
            $booking->paid_amount += $payment->amount;
            if ($booking->paid_amount >= $booking->total_amount) {
                $booking->payment_status = 'paid';
            } else {
                $booking->payment_status = 'partial';
            }
            $booking->save();
        }

        return response()->json([
            'success' => true,
            'payment' => $payment,
        ]);
    }

    public function show($id)
    {
        $payment = Payment::with('booking')->findOrFail($id);

        return response()->json([
            'success' => true,
            'payment' => $payment,
        ]);
    }
}

