<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'method',
        'path',
        'full_url',
        'request_headers',
        'request_body',
        'status_code',
        'response_headers',
        'response_size',
        'duration_ms',
        'ip_address',
        'user_agent',
        'controller',
        'action',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'duration_ms' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Don't log sensitive fields
    protected $hidden = [
        'request_headers',
        'request_body',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSlowRequests($query, $thresholdMs = 1000)
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    public function scopeServerErrors($query)
    {
        return $query->where('status_code', '>=', 500);
    }

    public function scopeForPath($query, $path)
    {
        return $query->where('path', 'like', $path . '%');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeLastHour($query)
    {
        return $query->where('created_at', '>=', now()->subHour());
    }

    // Static Methods
    public static function getMetrics($from = null, $to = null)
    {
        $query = self::query();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return [
            'total_requests' => $query->count(),
            'avg_response_time' => round($query->avg('duration_ms'), 2),
            'max_response_time' => round($query->max('duration_ms'), 2),
            'min_response_time' => round($query->min('duration_ms'), 2),
            'error_count' => (clone $query)->where('status_code', '>=', 400)->count(),
            'error_rate' => $query->count() > 0
                ? round(((clone $query)->where('status_code', '>=', 400)->count() / $query->count()) * 100, 2)
                : 0,
        ];
    }

    public static function getEndpointStats($from = null, $to = null)
    {
        $query = self::selectRaw('
            path, method,
            COUNT(*) as request_count,
            AVG(duration_ms) as avg_duration,
            MAX(duration_ms) as max_duration,
            SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count
        ')
            ->groupBy('path', 'method')
            ->orderByDesc('request_count');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->limit(50)->get();
    }
}
