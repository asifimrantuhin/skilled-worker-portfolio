<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'user_id',
        'endpoint',
        'method',
        'request_params',
        'response_status',
        'response_body',
        'is_processing',
        'processed_at',
        'expires_at',
    ];

    protected $casts = [
        'request_params' => 'array',
        'response_body' => 'array',
        'is_processing' => 'boolean',
        'processed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    // Helper Methods
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    public function isProcessing(): bool
    {
        return $this->is_processing;
    }

    public function hasResponse(): bool
    {
        return !is_null($this->processed_at);
    }

    public function markAsProcessing(): void
    {
        $this->update(['is_processing' => true]);
    }

    public function storeResponse(int $status, array $body): void
    {
        $this->update([
            'response_status' => $status,
            'response_body' => $body,
            'is_processing' => false,
            'processed_at' => now(),
        ]);
    }

    // Static Methods
    public static function generate(): string
    {
        return hash('sha256', Str::uuid()->toString() . microtime(true));
    }

    public static function findValid(string $key): ?self
    {
        return self::where('key', $key)
            ->valid()
            ->first();
    }

    public static function findOrCreate(string $key, int $userId = null, string $endpoint, string $method, array $params = []): self
    {
        $existing = self::findValid($key);

        if ($existing) {
            return $existing;
        }

        return self::create([
            'key' => $key,
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_params' => $params,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public static function cleanupExpired(): int
    {
        return self::expired()->delete();
    }
}
