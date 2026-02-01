<?php

namespace App\Console\Commands;

use App\Models\EscalationRule;
use App\Models\Ticket;
use App\Models\TicketSla;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTicketSlas extends Command
{
    protected $signature = 'tickets:check-slas {--apply-escalations : Also apply escalation rules}';

    protected $description = 'Check for SLA breaches and optionally apply escalation rules';

    public function handle(): int
    {
        $this->info('Checking SLA breaches...');

        // Check for SLA breaches
        $breachedCount = TicketSla::checkBreaches();
        $this->info("Marked {$breachedCount} SLAs as breached.");

        // Update tickets with breached SLAs
        $ticketsUpdated = Ticket::whereHas('slas', function ($query) {
            $query->where('status', 'breached');
        })->where('sla_breached', false)->update([
            'sla_breached' => true,
            'sla_status' => 'breached',
        ]);

        $this->info("Updated {$ticketsUpdated} tickets with SLA breach status.");

        // Apply escalation rules if requested
        if ($this->option('apply-escalations')) {
            $this->applyEscalationRules();
        }

        return Command::SUCCESS;
    }

    protected function applyEscalationRules(): void
    {
        $this->info('Applying escalation rules...');

        // Get time-based escalation rules
        $rules = EscalationRule::getTimeBased();

        if ($rules->isEmpty()) {
            $this->info('No active time-based escalation rules found.');
            return;
        }

        $escalatedCount = 0;

        // Get open tickets that might need escalation
        Ticket::open()
            ->where(function ($query) {
                $query->where('sla_breached', true)
                    ->orWhere('escalation_level', 0);
            })
            ->chunk(100, function ($tickets) use ($rules, &$escalatedCount) {
                foreach ($tickets as $ticket) {
                    foreach ($rules as $rule) {
                        if ($this->shouldApplyRule($rule, $ticket)) {
                            $this->applyRule($rule, $ticket);
                            $escalatedCount++;
                            break; // Apply only one rule per ticket
                        }
                    }
                }
            });

        $this->info("Applied escalation rules to {$escalatedCount} tickets.");
    }

    protected function shouldApplyRule(EscalationRule $rule, Ticket $ticket): bool
    {
        // Skip if ticket is already at or above this escalation level
        if ($ticket->escalation_level >= $rule->escalation_level) {
            return false;
        }

        // Check time threshold
        if ($rule->time_threshold_hours) {
            $ticketAge = $ticket->created_at->diffInHours(now());
            if ($ticketAge < $rule->time_threshold_hours) {
                return false;
            }
        }

        // Check if rule conditions match the ticket
        return $rule->matchesTicket($ticket);
    }

    protected function applyRule(EscalationRule $rule, Ticket $ticket): void
    {
        try {
            $rule->executeActions($ticket);

            Log::info("Applied escalation rule to ticket", [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'new_escalation_level' => $rule->escalation_level,
            ]);

            $this->line("  - Escalated ticket #{$ticket->ticket_number} using rule: {$rule->name}");
        } catch (\Exception $e) {
            Log::error("Failed to apply escalation rule", [
                'rule_id' => $rule->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("  - Failed to escalate ticket #{$ticket->ticket_number}: {$e->getMessage()}");
        }
    }
}
