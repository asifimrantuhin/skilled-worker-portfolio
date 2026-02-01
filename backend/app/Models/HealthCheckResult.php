<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthCheckResult extends Model
{
    protected $fillable = [
        'check_name',
        'status',
        'response_time_ms',
        'message',
        'details',
        'checked_at',
    ];

    protected $casts = [
        'response_time_ms' => 'decimal:2',
        'details' => 'array',
        'checked_at' => 'datetime',
    ];

    // Scopes
    public function scopeForCheck($query, $name)
    {
        return $query->where('check_name', $name);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('checked_at');
    }

    public function scopeUnhealthy($query)
    {
        return $query->where('status', 'unhealthy');
    }

    public function scopeDegraded($query)
    {
        return $query->whereIn('status', ['unhealthy', 'degraded']);
    }

    // Static Methods
    public static function record(string $checkName, string $status, float $responseTimeMs = null, string $message = null, array $details = []): self
    {
        return self::create([
            'check_name' => $checkName,
            'status' => $status,
            'response_time_ms' => $responseTimeMs,
            'message' => $message,
            'details' => $details,
            'checked_at' => now(),
        ]);
    }

    public static function getLatestStatus(): array
    {
        $latestChecks = self::selectRaw('check_name, MAX(id) as latest_id')
            ->groupBy('check_name')
            ->pluck('latest_id');

        return self::whereIn('id', $latestChecks)->get()->keyBy('check_name')->toArray();
    }

    public static function isSystemHealthy(): bool
    {
        $latestChecks = self::getLatestStatus();
        
        foreach ($latestChecks as $check) {
            if ($check['status'] === 'unhealthy') {
                return false;
            }
        }
        
        return true;
    }

    public static function getOverallStatus(): string
    {
        $latestChecks = self::getLatestStatus();
        
        $hasUnhealthy = false;
        $hasDegraded = false;
        
        foreach ($latestChecks as $check) {
            if ($check['status'] === 'unhealthy') {
                $hasUnhealthy = true;
            }
            if ($check['status'] === 'degraded') {
                $hasDegraded = true;
            }
        }
        
        if ($hasUnhealthy) {
            return 'unhealthy';
        }
        if ($hasDegraded) {
            return 'degraded';
        }
        return 'healthy';
    }
}
