<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'referral_code',
        'referred_email',
        'referred_name',
        'status',
        'reward_amount',
        'reward_type',
        'reward_paid',
        'registered_at',
        'first_booking_at',
        'reward_paid_at',
        'expires_at',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:2',
        'reward_paid' => 'boolean',
        'registered_at' => 'datetime',
        'first_booking_at' => 'datetime',
        'reward_paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($referral) {
            if (empty($referral->referral_code)) {
                $referral->referral_code = 'REF-' . strtoupper(Str::random(8));
            }
            if (empty($referral->expires_at)) {
                $referral->expires_at = now()->addDays(30);
            }
        });
    }

    // Relationships
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    // Scopes
    public function scopeForReferrer($query, $referrerId)
    {
        return $query->where('referrer_id', $referrerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereIn('status', ['pending', 'registered', 'booked']);
    }

    public function scopeRewardable($query)
    {
        return $query->where('status', 'booked')
            ->where('reward_paid', false);
    }

    // Helper Methods
    public function isExpired(): bool
    {
        return $this->expires_at < now() && $this->status === 'pending';
    }

    public function markAsRegistered(int $userId): void
    {
        $this->update([
            'referred_id' => $userId,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    public function markAsBooked(): void
    {
        $this->update([
            'status' => 'booked',
            'first_booking_at' => now(),
        ]);
    }

    public function markAsRewarded(float $amount = null): void
    {
        $this->update([
            'status' => 'rewarded',
            'reward_amount' => $amount ?? $this->reward_amount,
            'reward_paid' => true,
            'reward_paid_at' => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public static function findByCode(string $code): ?self
    {
        return self::where('referral_code', strtoupper($code))
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();
    }

    public static function generateForUser(int $userId, string $email, string $name = null): self
    {
        return self::create([
            'referrer_id' => $userId,
            'referred_email' => $email,
            'referred_name' => $name,
            'reward_type' => 'cash',
        ]);
    }
}
