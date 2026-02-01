<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentReconciliation extends Model
{
    protected $fillable = [
        'batch_id',
        'reconciliation_date',
        'payment_provider',
        'total_transactions',
        'matched_transactions',
        'mismatched_transactions',
        'missing_in_system',
        'missing_in_provider',
        'total_amount',
        'matched_amount',
        'discrepancy_amount',
        'status',
        'discrepancies',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'total_amount' => 'decimal:2',
        'matched_amount' => 'decimal:2',
        'discrepancy_amount' => 'decimal:2',
        'discrepancies' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reconciliation) {
            if (empty($reconciliation->batch_id)) {
                $reconciliation->batch_id = (string) Str::uuid();
            }
        });
    }

    // Scopes
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('payment_provider', $provider);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('reconciliation_date', $date);
    }

    public function scopeWithDiscrepancies($query)
    {
        return $query->where('discrepancy_amount', '>', 0);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper Methods
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasDiscrepancies(): bool
    {
        return $this->discrepancy_amount > 0 
            || $this->mismatched_transactions > 0 
            || $this->missing_in_system > 0 
            || $this->missing_in_provider > 0;
    }

    public function getMatchRate(): float
    {
        if ($this->total_transactions === 0) {
            return 100;
        }
        return round(($this->matched_transactions / $this->total_transactions) * 100, 2);
    }

    public function start(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function fail(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason,
            'completed_at' => now(),
        ]);
    }

    public function addDiscrepancy(array $discrepancy): void
    {
        $discrepancies = $this->discrepancies ?? [];
        $discrepancies[] = $discrepancy;
        $this->discrepancies = $discrepancies;
        $this->save();
    }

    // Static Methods
    public static function createForDate($date, string $provider): self
    {
        return self::create([
            'reconciliation_date' => $date,
            'payment_provider' => $provider,
        ]);
    }

    public static function getLatest(string $provider = null): ?self
    {
        $query = self::query();

        if ($provider) {
            $query->forProvider($provider);
        }

        return $query->orderByDesc('reconciliation_date')->first();
    }
}
