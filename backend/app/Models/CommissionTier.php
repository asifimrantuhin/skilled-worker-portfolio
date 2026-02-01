<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'min_bookings',
        'min_revenue',
        'commission_rate',
        'bonus_rate',
        'benefits',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'min_bookings' => 'decimal:2',
        'min_revenue' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'bonus_rate' => 'decimal:2',
        'benefits' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function agents()
    {
        return $this->hasMany(User::class, 'commission_tier_id');
    }

    public function tierHistories()
    {
        return $this->hasMany(AgentTierHistory::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_revenue');
    }

    // Helper Methods
    public static function getTierForAgent(int $agentId): ?self
    {
        $stats = self::getAgentMonthlyStats($agentId);

        return self::active()
            ->ordered()
            ->where(function ($query) use ($stats) {
                $query->where('min_bookings', '<=', $stats['bookings'])
                    ->where('min_revenue', '<=', $stats['revenue']);
            })
            ->orderBy('commission_rate', 'desc')
            ->first();
    }

    public static function getAgentMonthlyStats(int $agentId): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $bookings = \App\Models\Booking::where('agent_id', $agentId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->whereIn('status', ['confirmed', 'completed'])
            ->get();

        return [
            'bookings' => $bookings->count(),
            'revenue' => $bookings->sum('total_amount'),
        ];
    }

    public function getTotalCommissionRate(): float
    {
        return $this->commission_rate + $this->bonus_rate;
    }
}
