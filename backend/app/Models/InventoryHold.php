<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_id',
        'user_id',
        'travel_date',
        'slots_held',
        'hold_token',
        'expires_at',
        'status',
        'booking_id',
    ];

    protected $casts = [
        'travel_date' => 'date',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($hold) {
            if (empty($hold->hold_token)) {
                $hold->hold_token = Str::random(64);
            }
            if (empty($hold->expires_at)) {
                $hold->expires_at = now()->addMinutes(15);
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

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markAsConverted(int $bookingId): void
    {
        $this->update([
            'status' => 'converted',
            'booking_id' => $bookingId,
        ]);
    }

    public function release(): void
    {
        $this->update(['status' => 'released']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<=', now());
    }
}
