<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'error_hash',
        'request_id',
        'user_id',
        'exception_class',
        'message',
        'stack_trace',
        'file',
        'line',
        'severity',
        'context',
        'url',
        'method',
        'ip_address',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'occurrence_count',
        'first_occurred_at',
        'last_occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'first_occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('last_occurred_at', '>=', now()->subHours($hours));
    }

    // Helper Methods
    public function resolve(string $resolvedBy, string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
            'resolution_notes' => $notes,
        ]);
    }

    public function incrementOccurrence(): void
    {
        $this->increment('occurrence_count');
        $this->update(['last_occurred_at' => now()]);
    }

    public static function generateHash(\Throwable $e): string
    {
        return hash('sha256', $e::class . '|' . $e->getFile() . '|' . $e->getLine() . '|' . substr($e->getMessage(), 0, 100));
    }

    public static function logException(\Throwable $e, array $context = []): self
    {
        $hash = self::generateHash($e);

        $existing = self::where('error_hash', $hash)
            ->where('is_resolved', false)
            ->first();

        if ($existing) {
            $existing->incrementOccurrence();
            return $existing;
        }

        return self::create([
            'error_hash' => $hash,
            'request_id' => $context['request_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'exception_class' => $e::class,
            'message' => substr($e->getMessage(), 0, 1000),
            'stack_trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'severity' => $context['severity'] ?? 'error',
            'context' => $context,
            'url' => $context['url'] ?? null,
            'method' => $context['method'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'first_occurred_at' => now(),
            'last_occurred_at' => now(),
        ]);
    }

    public static function getTopErrors($limit = 10, $hours = 24)
    {
        return self::unresolved()
            ->recent($hours)
            ->orderByDesc('occurrence_count')
            ->limit($limit)
            ->get();
    }
}
