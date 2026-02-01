<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'inquiry_id',
        'booking_id',
        'user_id',
        'subject',
        'description',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
        'escalation_level',
        'escalated_at',
        'first_response_at',
        'sla_breached',
        'sla_status',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'first_response_at' => 'datetime',
        'sla_breached' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = 'TKT' . strtoupper(uniqid());
            }
        });

        static::created(function ($ticket) {
            // Create SLAs for the ticket
            $ticket->createDefaultSlas();
        });
    }

    // Relationships
    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    public function slas()
    {
        return $this->hasMany(TicketSla::class);
    }

    public function satisfactionSurveys()
    {
        return $this->morphMany(SatisfactionSurvey::class, 'surveyable');
    }

    // Scopes
    public function scopeEscalated($query)
    {
        return $query->where('escalation_level', '>', 0);
    }

    public function scopeByEscalationLevel($query, int $level)
    {
        return $query->where('escalation_level', $level);
    }

    public function scopeSlaBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    public function scopePendingSla($query)
    {
        return $query->where('sla_status', 'pending');
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('status', ['resolved', 'closed']);
    }

    // SLA Methods
    public function createDefaultSlas(): void
    {
        $firstResponseHours = TicketSla::getDefaultTargetHours($this->priority ?? 'medium', 'first_response');
        $resolutionHours = TicketSla::getDefaultTargetHours($this->priority ?? 'medium', 'resolution');

        TicketSla::createForTicket($this, 'first_response', $firstResponseHours);
        TicketSla::createForTicket($this, 'resolution', $resolutionHours);

        $this->update(['sla_status' => 'pending']);
    }

    public function markFirstResponse(): void
    {
        if ($this->first_response_at) {
            return;
        }

        $this->update(['first_response_at' => now()]);

        $firstResponseSla = $this->slas()->byType('first_response')->pending()->first();
        if ($firstResponseSla) {
            $firstResponseSla->markAchieved();
        }
    }

    public function checkSlaStatus(): void
    {
        $pendingSlas = $this->slas()->pending()->get();
        $hasBreached = false;

        foreach ($pendingSlas as $sla) {
            if ($sla->isOverdue()) {
                $sla->markBreached();
                $hasBreached = true;
            }
        }

        if ($hasBreached) {
            $this->update([
                'sla_breached' => true,
                'sla_status' => 'breached',
            ]);
        }
    }

    // Escalation Methods
    public function escalate(int $level = null, string $reason = null): void
    {
        $newLevel = $level ?? ($this->escalation_level + 1);

        $this->update([
            'escalation_level' => $newLevel,
            'escalated_at' => now(),
        ]);

        // Log the escalation as a system reply
        $this->replies()->create([
            'user_id' => null,
            'message' => "Ticket escalated to level {$newLevel}" . ($reason ? ": {$reason}" : ''),
            'is_internal' => true,
        ]);
    }

    public function deescalate(): void
    {
        if ($this->escalation_level > 0) {
            $this->update([
                'escalation_level' => $this->escalation_level - 1,
            ]);
        }
    }

    public function isEscalated(): bool
    {
        return $this->escalation_level > 0;
    }

    // Resolution Methods
    public function resolve(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // Mark resolution SLA as achieved
        $resolutionSla = $this->slas()->byType('resolution')->pending()->first();
        if ($resolutionSla) {
            $resolutionSla->markAchieved();
        }

        // Update overall SLA status
        $this->updateSlaStatus();

        // Send satisfaction survey
        SatisfactionSurvey::createForTicket($this);
    }

    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'resolved_at' => null,
        ]);
    }

    protected function updateSlaStatus(): void
    {
        $slas = $this->slas;
        
        if ($slas->where('status', 'breached')->isNotEmpty()) {
            $this->update(['sla_status' => 'breached']);
        } elseif ($slas->where('status', 'pending')->isEmpty()) {
            $this->update(['sla_status' => 'achieved']);
        }
    }

    // Helper Methods
    public function getFirstResponseSla(): ?TicketSla
    {
        return $this->slas()->byType('first_response')->first();
    }

    public function getResolutionSla(): ?TicketSla
    {
        return $this->slas()->byType('resolution')->first();
    }

    public function getTimeToFirstResponse(): ?int
    {
        if (!$this->first_response_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->first_response_at);
    }

    public function getTimeToResolution(): ?int
    {
        if (!$this->resolved_at) {
            return null;
        }

        return $this->created_at->diffInMinutes($this->resolved_at);
    }
}

