<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_number',
        'package_id',
        'user_id',
        'agent_id',
        'travel_date',
        'adults',
        'children',
        'infants',
        'package_price',
        'discount',
        'tax',
        'total_amount',
        'paid_amount',
        'status',
        'payment_status',
        'travelers_info',
        'special_requests',
        'promo_code_id',
        'promo_discount',
        'cancellation_fee',
        'refund_amount',
        'hold_token',
        'cancellation_reason',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'travel_date' => 'date',
        'package_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'promo_discount' => 'decimal:2',
        'cancellation_fee' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'travelers_info' => 'array',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = 'BK' . strtoupper(uniqid());
            }
        });
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function commission()
    {
        return $this->hasOne(AgentCommission::class);
    }

    public function tickets()

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function inventoryHold()
    {
        return $this->hasOne(InventoryHold::class);
    }

    public function getDaysUntilTravel(): int
    {
        return max(0, now()->startOfDay()->diffInDays($this->travel_date, false));
    }

    public function getCancellationPolicy(): ?CancellationPolicy
    {
        return $this->package?->cancellationPolicy ?? CancellationPolicy::getDefault();
    }

    public function calculateCancellationRefund(): array
    {
        $policy = $this->getCancellationPolicy();

        if (! $policy) {
            return [
                'refund_percentage' => 0,
                'refund_amount' => 0,
                'cancellation_fee' => $this->paid_amount,
                'rule_applied' => null,
            ];
        }

        return $policy->calculateRefund($this->paid_amount, $this->getDaysUntilTravel());
    }
    {
        return $this->hasMany(Ticket::class);
    }
}

