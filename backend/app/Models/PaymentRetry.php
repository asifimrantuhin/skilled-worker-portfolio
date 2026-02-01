<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRetry extends Model
{
    protected $fillable = [
        'payment_id',
        'booking_id',
        'user_id',
        'payment_method',
        'amount',
        'currency',
        'payment_data',
        'retry_count',
        'max_retries',
        'status',
        'last_error',
        'last_error_code',
        'next_retry_at',
        'last_retry_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_data' => 'array',
        'next_retry_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    // Relationships
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'pending')
            ->where('next_retry_at', '<=', now())
            ->where('retry_count', '<', \DB::raw('max_retries'));
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helper Methods
    public function canRetry(): bool
    {
        return $this->status === 'pending' 
            && $this->retry_count < $this->max_retries
            && ($this->next_retry_at === null || $this->next_retry_at <= now());
    }

    public function incrementRetry(): void
    {
        $this->retry_count++;
        $this->last_retry_at = now();
        
        // Exponential backoff: 5min, 15min, 45min, etc.
        $delayMinutes = pow(3, $this->retry_count) * 5;
        $this->next_retry_at = now()->addMinutes(min($delayMinutes, 1440)); // Max 24 hours
        
        $this->save();
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markAsSucceeded(): void
    {
        $this->update([
            'status' => 'succeeded',
            'next_retry_at' => null,
        ]);
    }

    public function markAsFailed(string $error, string $errorCode = null): void
    {
        $this->last_error = $error;
        $this->last_error_code = $errorCode;

        if ($this->retry_count >= $this->max_retries) {
            $this->status = 'failed';
            $this->next_retry_at = null;
        } else {
            $this->status = 'pending';
            $this->incrementRetry();
        }

        $this->save();
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'next_retry_at' => null,
        ]);
    }

    // Static Methods
    public static function createFromPayment(Payment $payment, string $error = null): self
    {
        return self::create([
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'user_id' => $payment->booking->user_id,
            'payment_method' => $payment->payment_method,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'BDT',
            'payment_data' => [
                'original_transaction_id' => $payment->transaction_id,
            ],
            'last_error' => $error,
            'next_retry_at' => now()->addMinutes(5),
        ]);
    }

    public static function getRetryQueue(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::readyForRetry()
            ->with(['payment', 'booking', 'user'])
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();
    }
}
