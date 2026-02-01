<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowUpReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'reminder_type',
        'remindable_type',
        'remindable_id',
        'title',
        'notes',
        'remind_at',
        'priority',
        'status',
        'completed_at',
        'snoozed_until',
        'snooze_count',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'snooze_count' => 'integer',
    ];

    // Relationships
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function remindable()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->where('remind_at', '<=', now())
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'snoozed')
                            ->where('snoozed_until', '<=', now());
                    });
            });
    }

    public function scopeUpcoming($query, $hours = 24)
    {
        return $query->where('status', 'pending')
            ->where('remind_at', '>', now())
            ->where('remind_at', '<=', now()->addHours($hours));
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('remind_at', '<', now());
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    // Helper Methods
    public function isDue(): bool
    {
        if ($this->status === 'snoozed' && $this->snoozed_until) {
            return $this->snoozed_until <= now();
        }
        return $this->remind_at <= now() && $this->status === 'pending';
    }

    public function isOverdue(): bool
    {
        return $this->remind_at < now() && $this->status === 'pending';
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function snooze(int $hours = 1): void
    {
        $this->update([
            'status' => 'snoozed',
            'snoozed_until' => now()->addHours($hours),
            'snooze_count' => $this->snooze_count + 1,
        ]);
    }

    public function unsnooze(): void
    {
        $this->update([
            'status' => 'pending',
            'snoozed_until' => null,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function reschedule(\DateTime $newTime): void
    {
        $this->update([
            'remind_at' => $newTime,
            'status' => 'pending',
            'snoozed_until' => null,
        ]);
    }
}
