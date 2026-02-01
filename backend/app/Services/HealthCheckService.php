<?php

namespace App\Services;

use App\Models\HealthCheckResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheckService
{
    protected array $checks = [];

    public function __construct()
    {
        $this->registerDefaultChecks();
    }

    protected function registerDefaultChecks(): void
    {
        $this->checks = [
            'database' => [$this, 'checkDatabase'],
            'cache' => [$this, 'checkCache'],
            'storage' => [$this, 'checkStorage'],
            'queue' => [$this, 'checkQueue'],
        ];
    }

    public function registerCheck(string $name, callable $callback): void
    {
        $this->checks[$name] = $callback;
    }

    public function runAllChecks(): array
    {
        $results = [];
        $overallStatus = 'healthy';

        foreach ($this->checks as $name => $callback) {
            $result = $this->runCheck($name, $callback);
            $results[$name] = $result;

            // Record to database
            HealthCheckResult::record(
                $name,
                $result['status'],
                $result['response_time_ms'] ?? null,
                $result['message'] ?? null,
                $result['details'] ?? []
            );

            // Update overall status
            if ($result['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
            } elseif ($result['status'] === 'degraded' && $overallStatus !== 'unhealthy') {
                $overallStatus = 'degraded';
            }
        }

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'checks' => $results,
        ];
    }

    public function runCheck(string $name, callable $callback = null): array
    {
        $callback = $callback ?? $this->checks[$name] ?? null;

        if (!$callback) {
            return [
                'status' => 'unhealthy',
                'message' => 'Unknown health check',
            ];
        }

        $startTime = microtime(true);

        try {
            $result = $callback();
            $responseTime = (microtime(true) - $startTime) * 1000;

            return array_merge($result, [
                'response_time_ms' => round($responseTime, 2),
            ]);
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
                'response_time_ms' => round($responseTime, 2),
            ];
        }
    }

    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = (microtime(true) - $start) * 1000;

            // Get connection info
            $connectionName = config('database.default');
            $connections = DB::connection()->select('SHOW STATUS LIKE "Threads_connected"');
            $maxConnections = DB::connection()->select('SHOW VARIABLES LIKE "max_connections"');

            $currentConnections = $connections[0]->Value ?? 0;
            $maxConnectionsValue = $maxConnections[0]->Value ?? 151;
            $connectionUsage = ($currentConnections / $maxConnectionsValue) * 100;

            $status = 'healthy';
            if ($connectionUsage > 80) {
                $status = 'degraded';
            }
            if ($connectionUsage > 95) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'message' => 'Database connection successful',
                'details' => [
                    'connection' => $connectionName,
                    'latency_ms' => round($latency, 2),
                    'connections_used' => $currentConnections,
                    'max_connections' => $maxConnectionsValue,
                    'connection_usage_percent' => round($connectionUsage, 2),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test_value';

            // Test write
            Cache::put($key, $value, 10);

            // Test read
            $retrieved = Cache::get($key);

            // Test delete
            Cache::forget($key);

            if ($retrieved !== $value) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache read/write mismatch',
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Cache is functioning properly',
                'details' => [
                    'driver' => config('cache.default'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache check failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $disk = config('filesystems.default');
            $testFile = 'health_check_' . time() . '.txt';

            // Test write
            Storage::put($testFile, 'health check');

            // Test read
            $content = Storage::get($testFile);

            // Test delete
            Storage::delete($testFile);

            if ($content !== 'health check') {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Storage read/write mismatch',
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Storage is functioning properly',
                'details' => [
                    'disk' => $disk,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed: ' . $e->getMessage(),
            ];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            // For sync driver, just return healthy
            if ($driver === 'sync') {
                return [
                    'status' => 'healthy',
                    'message' => 'Queue using sync driver',
                    'details' => [
                        'driver' => $driver,
                    ],
                ];
            }

            // For database driver, check the jobs table
            if ($driver === 'database') {
                $pendingJobs = DB::table('jobs')->count();
                $failedJobs = DB::table('failed_jobs')->count();

                $status = 'healthy';
                if ($failedJobs > 10) {
                    $status = 'degraded';
                }
                if ($pendingJobs > 1000) {
                    $status = 'degraded';
                }

                return [
                    'status' => $status,
                    'message' => 'Queue is accessible',
                    'details' => [
                        'driver' => $driver,
                        'pending_jobs' => $pendingJobs,
                        'failed_jobs' => $failedJobs,
                    ],
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Queue driver configured',
                'details' => [
                    'driver' => $driver,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'degraded',
                'message' => 'Queue check failed: ' . $e->getMessage(),
            ];
        }
    }

    public function getReadinessStatus(): array
    {
        // Check only critical services for readiness
        $criticalChecks = ['database'];
        $ready = true;

        foreach ($criticalChecks as $check) {
            if (isset($this->checks[$check])) {
                $result = $this->runCheck($check);
                if ($result['status'] === 'unhealthy') {
                    $ready = false;
                    break;
                }
            }
        }

        return [
            'ready' => $ready,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function getLivenessStatus(): array
    {
        return [
            'alive' => true,
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
        ];
    }
}
