<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'transaction_id',
        'idempotency_key',
        'provider_transaction_id',
        'payment_method',
        'amount',
        'status',
        'currency',
        'payment_data',
        'provider_response',
        'gateway_transaction_id',
        'failure_reason',
        'paid_at',
        'is_reconciled',
        'reconciled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_data' => 'array',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'is_reconciled' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->transaction_id)) {
                $payment->transaction_id = 'TXN' . strtoupper(uniqid());
            }
        });
    }

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function retries()
    {
        return $this->hasMany(PaymentRetry::class);
    }

    // Scopes
    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // Helper Methods
    public function markAsReconciled(): void
    {
        $this->update([
            'is_reconciled' => true,
            'reconciled_at' => now(),
        ]);
    }

    public function createRetry(string $error = null): PaymentRetry
    {
        return PaymentRetry::createFromPayment($this, $error);
    }
}

