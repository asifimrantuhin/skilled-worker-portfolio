<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Models\PerformanceMetric;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    /**
     * Paths that should not be logged
     */
    protected $excludedPaths = [
        'api/health',
        'api/health/*',
    ];

    /**
     * Headers that should be redacted
     */
    protected $sensitiveHeaders = [
        'authorization',
        'cookie',
        'x-api-key',
    ];

    /**
     * Body fields that should be redacted
     */
    protected $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'credit_card',
        'card_number',
        'cvv',
        'secret',
        'token',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip excluded paths
        if ($this->isExcluded($request)) {
            return $next($request);
        }

        // Generate unique request ID
        $requestId = (string) Str::uuid();
        $request->headers->set('X-Request-ID', $requestId);

        $startTime = microtime(true);
        $startedAt = now();

        // Process the request
        $response = $next($request);

        // Calculate duration
        $duration = (microtime(true) - $startTime) * 1000;

        // Add request ID to response
        $response->headers->set('X-Request-ID', $requestId);

        // Log the request asynchronously if possible
        try {
            $this->logRequest($request, $response, $requestId, $duration, $startedAt);
            
            // Record performance metric
            PerformanceMetric::recordApiLatency($request->path(), $duration);
        } catch (\Exception $e) {
            // Don't let logging errors break the response
            \Log::error('Failed to log API request: ' . $e->getMessage());
        }

        return $response;
    }

    protected function isExcluded(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->excludedPaths as $excludedPath) {
            if (Str::is($excludedPath, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function logRequest(Request $request, Response $response, string $requestId, float $duration, $startedAt): void
    {
        // Get route info
        $route = $request->route();
        $controller = null;
        $action = null;

        if ($route) {
            $actionName = $route->getActionName();
            if (str_contains($actionName, '@')) {
                [$controller, $action] = explode('@', $actionName);
            } elseif (str_contains($actionName, '\\')) {
                $controller = $actionName;
            }
        }

        // Sanitize headers
        $requestHeaders = $this->sanitizeHeaders($request->headers->all());

        // Sanitize body
        $requestBody = $this->sanitizeBody($request->all());

        // Get response size
        $responseContent = $response->getContent();
        $responseSize = $responseContent ? strlen($responseContent) : 0;

        ApiLog::create([
            'request_id' => $requestId,
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => '/' . $request->path(),
            'full_url' => $request->fullUrl(),
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'status_code' => $response->getStatusCode(),
            'response_headers' => $response->headers->all(),
            'response_size' => $responseSize,
            'duration_ms' => round($duration, 2),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'controller' => $controller,
            'action' => $action,
            'started_at' => $startedAt,
            'completed_at' => now(),
        ]);
    }

    protected function sanitizeHeaders(array $headers): array
    {
        foreach ($this->sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }
        return $headers;
    }

    protected function sanitizeBody(array $body): array
    {
        foreach ($body as $key => $value) {
            if (in_array(strtolower($key), $this->sensitiveFields)) {
                $body[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $body[$key] = $this->sanitizeBody($value);
            }
        }
        return $body;
    }
}
