<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TicketSla extends Model
{
    use HasFactory;

    protected $table = 'ticket_slas';

    protected $fillable = [
        'ticket_id',
        'sla_type',
        'target_at',
        'achieved_at',
        'breached_at',
        'status',
    ];

    protected $casts = [
        'target_at' => 'datetime',
        'achieved_at' => 'datetime',
        'breached_at' => 'datetime',
    ];

    // Relationships
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAchieved($query)
    {
        return $query->where('status', 'achieved');
    }

    public function scopeBreached($query)
    {
        return $query->where('status', 'breached');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('sla_type', $type);
    }

    public function scopeOverdue($query)
    {
        return $query->pending()->where('target_at', '<', now());
    }

    // Helper Methods
    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->target_at < now();
    }

    public function remainingTime(): ?int
    {
        if ($this->status !== 'pending') {
            return null;
        }

        return now()->diffInMinutes($this->target_at, false);
    }

    public function remainingTimeFormatted(): ?string
    {
        $minutes = $this->remainingTime();
        
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 0) {
            return 'Overdue by ' . abs($minutes) . ' minutes';
        }

        if ($minutes < 60) {
            return $minutes . ' minutes remaining';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        return "{$hours}h {$mins}m remaining";
    }

    public function markAchieved(): void
    {
        $this->update([
            'status' => 'achieved',
            'achieved_at' => now(),
        ]);
    }

    public function markBreached(): void
    {
        $this->update([
            'status' => 'breached',
            'breached_at' => now(),
        ]);
    }

    // Static Methods
    public static function createForTicket(Ticket $ticket, string $type, int $targetHours): self
    {
        return self::create([
            'ticket_id' => $ticket->id,
            'sla_type' => $type,
            'target_at' => now()->addHours($targetHours),
            'status' => 'pending',
        ]);
    }

    public static function getDefaultTargetHours(string $priority, string $type): int
    {
        $defaults = [
            'first_response' => [
                'critical' => 1,
                'high' => 4,
                'medium' => 8,
                'low' => 24,
            ],
            'resolution' => [
                'critical' => 4,
                'high' => 24,
                'medium' => 48,
                'low' => 72,
            ],
        ];

        return $defaults[$type][$priority] ?? 24;
    }

    public static function checkBreaches(): int
    {
        $breachedCount = 0;

        self::pending()
            ->where('target_at', '<', now())
            ->chunk(100, function ($slas) use (&$breachedCount) {
                foreach ($slas as $sla) {
                    $sla->markBreached();
                    $sla->ticket->update(['sla_breached' => true, 'sla_status' => 'breached']);
                    $breachedCount++;
                }
            });

        return $breachedCount;
    }
}
