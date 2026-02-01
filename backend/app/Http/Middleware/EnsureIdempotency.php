<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to POST/PUT/PATCH requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // If no key provided, proceed normally
        if (!$idempotencyKey) {
            return $next($request);
        }

        // Validate key format (must be a valid SHA-256 hash or UUID format)
        if (!$this->isValidKey($idempotencyKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Idempotency-Key format',
            ], 400);
        }

        // Check for existing key
        $existing = IdempotencyKey::findValid($idempotencyKey);

        if ($existing) {
            // If still processing, return conflict
            if ($existing->isProcessing()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request is still being processed',
                ], 409);
            }

            // If already processed, return cached response
            if ($existing->hasResponse()) {
                return response()->json(
                    $existing->response_body,
                    $existing->response_status
                )->header('Idempotency-Replayed', 'true');
            }
        }

        // Create or get idempotency record
        $record = IdempotencyKey::findOrCreate(
            $idempotencyKey,
            $request->user()?->id,
            $request->path(),
            $request->method(),
            $request->except(['password', 'password_confirmation'])
        );

        // Mark as processing
        $record->markAsProcessing();

        // Store record in request for later use
        $request->attributes->set('idempotency_record', $record);

        try {
            // Process the request
            $response = $next($request);

            // Store the response
            $record->storeResponse(
                $response->getStatusCode(),
                json_decode($response->getContent(), true) ?? []
            );

            return $response;
        } catch (\Exception $e) {
            // On error, release the processing lock
            $record->update(['is_processing' => false]);
            throw $e;
        }
    }

    protected function isValidKey(string $key): bool
    {
        // Accept SHA-256 hash (64 hex chars) or UUID format
        return preg_match('/^[a-f0-9]{64}$/i', $key) 
            || preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $key);
    }
}
