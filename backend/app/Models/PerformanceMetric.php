<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    protected $fillable = [
        'metric_name',
        'metric_type',
        'value',
        'tags',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'tags' => 'array',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    // Scopes
    public function scopeForMetric($query, $name)
    {
        return $query->where('metric_name', $name);
    }

    public function scopeInTimeRange($query, $from, $to)
    {
        return $query->whereBetween('recorded_at', [$from, $to]);
    }

    public function scopeLastHours($query, $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    // Static Methods
    public static function record(string $name, float $value, string $type = 'gauge', array $tags = [], array $metadata = []): self
    {
        return self::create([
            'metric_name' => $name,
            'metric_type' => $type,
            'value' => $value,
            'tags' => $tags,
            'metadata' => $metadata,
            'recorded_at' => now(),
        ]);
    }

    public static function increment(string $name, float $amount = 1, array $tags = []): self
    {
        return self::record($name, $amount, 'counter', $tags);
    }

    public static function gauge(string $name, float $value, array $tags = []): self
    {
        return self::record($name, $value, 'gauge', $tags);
    }

    public static function histogram(string $name, float $value, array $tags = []): self
    {
        return self::record($name, $value, 'histogram', $tags);
    }

    public static function getAggregatedStats(string $name, $from = null, $to = null): array
    {
        $query = self::forMetric($name);

        if ($from) {
            $query->where('recorded_at', '>=', $from);
        }
        if ($to) {
            $query->where('recorded_at', '<=', $to);
        }

        return [
            'metric' => $name,
            'count' => $query->count(),
            'sum' => round($query->sum('value'), 4),
            'avg' => round($query->avg('value'), 4),
            'min' => round($query->min('value'), 4),
            'max' => round($query->max('value'), 4),
        ];
    }

    public static function getTimeSeries(string $name, string $interval = '1 hour', $from = null, $to = null): array
    {
        $from = $from ?? now()->subDay();
        $to = $to ?? now();

        // This is a simplified version - in production you'd use DB-specific date functions
        return self::forMetric($name)
            ->inTimeRange($from, $to)
            ->selectRaw('DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00:00") as time_bucket, AVG(value) as avg_value, COUNT(*) as count')
            ->groupBy('time_bucket')
            ->orderBy('time_bucket')
            ->get()
            ->toArray();
    }

    public static function recordApiLatency(string $endpoint, float $durationMs): self
    {
        return self::histogram('api.latency', $durationMs, ['endpoint' => $endpoint]);
    }

    public static function recordDatabaseQuery(float $durationMs, string $query = null): self
    {
        return self::histogram('db.query_time', $durationMs, ['query' => $query ? substr($query, 0, 100) : null]);
    }

    public static function recordMemoryUsage(): self
    {
        return self::gauge('app.memory_usage', memory_get_usage(true) / 1024 / 1024);
    }
}
