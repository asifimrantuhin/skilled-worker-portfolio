<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\ErrorLog;
use App\Models\HealthCheckResult;
use App\Models\PerformanceMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Get overview dashboard metrics
     */
    public function dashboard(Request $request)
    {
        $from = $request->get('from', now()->subDay());
        $to = $request->get('to', now());

        // API metrics
        $apiMetrics = ApiLog::getMetrics($from, $to);

        // Top endpoints
        $topEndpoints = ApiLog::getEndpointStats($from, $to)->take(10);

        // Error summary
        $errorSummary = [
            'total_errors' => ErrorLog::whereBetween('created_at', [$from, $to])->count(),
            'unresolved' => ErrorLog::unresolved()->count(),
            'critical' => ErrorLog::critical()->unresolved()->count(),
            'top_errors' => ErrorLog::getTopErrors(5, 24),
        ];

        // Health status
        $healthStatus = HealthCheckResult::getOverallStatus();
        $latestChecks = HealthCheckResult::getLatestStatus();

        // Performance trends
        $performanceTrends = $this->getPerformanceTrends($from, $to);

        return response()->json([
            'success' => true,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'api_metrics' => $apiMetrics,
            'top_endpoints' => $topEndpoints,
            'error_summary' => $errorSummary,
            'health' => [
                'status' => $healthStatus,
                'checks' => $latestChecks,
            ],
            'performance_trends' => $performanceTrends,
        ]);
    }

    /**
     * Get API request logs
     */
    public function apiLogs(Request $request)
    {
        $query = ApiLog::with('user:id,name,email');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status_code')) {
            $query->where('status_code', $request->status_code);
        }

        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        if ($request->has('path')) {
            $query->where('path', 'like', '%' . $request->path . '%');
        }

        if ($request->boolean('errors_only')) {
            $query->errors();
        }

        if ($request->boolean('slow_only')) {
            $query->slowRequests($request->get('threshold', 1000));
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Get error logs
     */
    public function errorLogs(Request $request)
    {
        $query = ErrorLog::with('user:id,name,email');

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('exception_class')) {
            $query->where('exception_class', 'like', '%' . $request->exception_class . '%');
        }

        if ($request->boolean('unresolved_only')) {
            $query->unresolved();
        }

        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        $errors = $query->orderByDesc('last_occurred_at')
            ->paginate($request->get('per_page', 25));

        return response()->json([
            'success' => true,
            'errors' => $errors,
        ]);
    }

    /**
     * Get single error details
     */
    public function errorDetail($id)
    {
        $error = ErrorLog::with('user:id,name,email')->findOrFail($id);

        return response()->json([
            'success' => true,
            'error' => $error,
        ]);
    }

    /**
     * Resolve an error
     */
    public function resolveError(Request $request, $id)
    {
        $error = ErrorLog::findOrFail($id);

        $error->resolve(
            $request->user()->name,
            $request->get('notes')
        );

        return response()->json([
            'success' => true,
            'message' => 'Error marked as resolved',
            'error' => $error,
        ]);
    }

    /**
     * Bulk resolve errors
     */
    public function bulkResolveErrors(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:error_logs,id',
            'notes' => 'nullable|string',
        ]);

        ErrorLog::whereIn('id', $request->ids)->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $request->user()->name,
            'resolution_notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => count($request->ids) . ' error(s) resolved',
        ]);
    }

    /**
     * Get performance metrics
     */
    public function performanceMetrics(Request $request)
    {
        $from = $request->get('from', now()->subDay());
        $to = $request->get('to', now());
        $metricName = $request->get('metric');

        if ($metricName) {
            $stats = PerformanceMetric::getAggregatedStats($metricName, $from, $to);
            $timeSeries = PerformanceMetric::getTimeSeries($metricName, '1 hour', $from, $to);

            return response()->json([
                'success' => true,
                'metric' => $metricName,
                'stats' => $stats,
                'time_series' => $timeSeries,
            ]);
        }

        // Get all metric names
        $metrics = PerformanceMetric::select('metric_name')
            ->distinct()
            ->pluck('metric_name');

        $allStats = [];
        foreach ($metrics as $name) {
            $allStats[$name] = PerformanceMetric::getAggregatedStats($name, $from, $to);
        }

        return response()->json([
            'success' => true,
            'period' => ['from' => $from, 'to' => $to],
            'metrics' => $allStats,
        ]);
    }

    /**
     * Get health check history
     */
    public function healthHistory(Request $request)
    {
        $checkName = $request->get('check');
        $from = $request->get('from', now()->subDay());
        $to = $request->get('to', now());

        $query = HealthCheckResult::whereBetween('checked_at', [$from, $to]);

        if ($checkName) {
            $query->where('check_name', $checkName);
        }

        $history = $query->orderByDesc('checked_at')
            ->paginate($request->get('per_page', 100));

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    protected function getPerformanceTrends($from, $to): array
    {
        // Get hourly request counts and avg latency
        $hourlyData = ApiLog::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                COUNT(*) as request_count,
                AVG(duration_ms) as avg_latency,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count
            ')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return [
            'hourly_requests' => $hourlyData->pluck('request_count', 'hour'),
            'hourly_latency' => $hourlyData->pluck('avg_latency', 'hour'),
            'hourly_errors' => $hourlyData->pluck('error_count', 'hour'),
        ];
    }

    /**
     * Get real-time stats (last 5 minutes)
     */
    public function realtime()
    {
        $fiveMinutesAgo = now()->subMinutes(5);

        $stats = [
            'requests' => ApiLog::where('created_at', '>=', $fiveMinutesAgo)->count(),
            'errors' => ApiLog::where('created_at', '>=', $fiveMinutesAgo)->errors()->count(),
            'avg_latency' => round(ApiLog::where('created_at', '>=', $fiveMinutesAgo)->avg('duration_ms'), 2),
            'active_users' => ApiLog::where('created_at', '>=', $fiveMinutesAgo)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
        ];

        return response()->json([
            'success' => true,
            'period' => '5 minutes',
            'stats' => $stats,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
