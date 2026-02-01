<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\ErrorLog;
use App\Models\HealthCheckResult;
use App\Models\PerformanceMetric;
use App\Services\HealthCheckService;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    protected HealthCheckService $healthService;

    public function __construct(HealthCheckService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Full health check - all services
     */
    public function health()
    {
        $result = $this->healthService->runAllChecks();

        $statusCode = match ($result['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 500,
        };

        return response()->json($result, $statusCode);
    }

    /**
     * Liveness probe - is the app running?
     */
    public function liveness()
    {
        return response()->json(
            $this->healthService->getLivenessStatus()
        );
    }

    /**
     * Readiness probe - is the app ready to serve traffic?
     */
    public function readiness()
    {
        $result = $this->healthService->getReadinessStatus();
        $statusCode = $result['ready'] ? 200 : 503;

        return response()->json($result, $statusCode);
    }

    /**
     * Individual health check
     */
    public function check(Request $request, $checkName)
    {
        $result = $this->healthService->runCheck($checkName);

        $statusCode = match ($result['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 500,
        };

        return response()->json($result, $statusCode);
    }
}
