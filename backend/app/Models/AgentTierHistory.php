<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentTierHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'commission_tier_id',
        'effective_from',
        'effective_until',
        'monthly_bookings',
        'monthly_revenue',
        'reason',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'monthly_bookings' => 'integer',
        'monthly_revenue' => 'decimal:2',
    ];

    // Relationships
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function tier()
    {
        return $this->belongsTo(CommissionTier::class, 'commission_tier_id');
    }

    // Scopes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('effective_until')
            ->orWhere('effective_until', '>=', now());
    }

    // Helper Methods
    public function isCurrent(): bool
    {
        return is_null($this->effective_until) || $this->effective_until >= now();
    }

    public static function recordTierChange(int $agentId, int $tierId, string $reason = null): self
    {
        // End current tier
        self::where('agent_id', $agentId)
            ->whereNull('effective_until')
            ->update(['effective_until' => now()->subDay()]);

        // Get current stats
        $stats = CommissionTier::getAgentMonthlyStats($agentId);

        // Create new tier history
        return self::create([
            'agent_id' => $agentId,
            'commission_tier_id' => $tierId,
            'effective_from' => now(),
            'monthly_bookings' => $stats['bookings'],
            'monthly_revenue' => $stats['revenue'],
            'reason' => $reason,
        ]);
    }
}
