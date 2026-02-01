<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscalationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'conditions',
        'actions',
        'escalation_level',
        'time_threshold_hours',
        'notify_customer',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'notify_customer' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTriggerType($query, string $type)
    {
        return $query->where('trigger_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority');
    }

    // Helper Methods
    public function matchesTicket(Ticket $ticket): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$this->evaluateCondition($condition, $ticket)) {
                return false;
            }
        }
        return true;
    }

    protected function evaluateCondition(array $condition, Ticket $ticket): bool
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$field) {
            return true;
        }

        $ticketValue = $ticket->{$field} ?? null;

        switch ($operator) {
            case 'equals':
                return $ticketValue == $value;
            case 'not_equals':
                return $ticketValue != $value;
            case 'in':
                return in_array($ticketValue, (array) $value);
            case 'not_in':
                return !in_array($ticketValue, (array) $value);
            case 'greater_than':
                return $ticketValue > $value;
            case 'less_than':
                return $ticketValue < $value;
            case 'is_null':
                return is_null($ticketValue);
            case 'is_not_null':
                return !is_null($ticketValue);
            default:
                return true;
        }
    }

    public function executeActions(Ticket $ticket): void
    {
        foreach ($this->actions as $action) {
            $this->executeAction($action, $ticket);
        }
    }

    protected function executeAction(array $action, Ticket $ticket): void
    {
        $type = $action['type'] ?? null;

        switch ($type) {
            case 'assign_to':
                $ticket->update(['assigned_to' => $action['user_id']]);
                break;
            case 'change_priority':
                $ticket->update(['priority' => $action['priority']]);
                break;
            case 'add_tag':
                // Implementation for tags if you have them
                break;
            case 'notify':
                // Send notification to specified users
                break;
            case 'escalate':
                $ticket->escalate($this->escalation_level, $action['reason'] ?? 'Auto-escalated by rule');
                break;
        }
    }

    public static function getTimeBased(): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->byTriggerType('time_based')
            ->ordered()
            ->get();
    }
}
