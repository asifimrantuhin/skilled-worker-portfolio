<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ReconcilePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile 
                            {--date= : The date to reconcile (default: yesterday)}
                            {--provider= : Payment provider (stripe, sslcommerz)}
                            {--all : Reconcile all providers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile payments with payment providers';

    protected PaymentReconciliationService $reconciliationService;

    public function __construct(PaymentReconciliationService $reconciliationService)
    {
        parent::__construct();
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $providers = $this->getProviders();

        $this->info("Running payment reconciliation for {$date->toDateString()}");
        $this->newLine();

        foreach ($providers as $provider) {
            $this->line("Reconciling {$provider}...");

            try {
                $reconciliation = $this->reconciliationService->reconcile($date, $provider);

                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Total Transactions', $reconciliation->total_transactions],
                        ['Matched', $reconciliation->matched_transactions],
                        ['Mismatched', $reconciliation->mismatched_transactions],
                        ['Missing in System', $reconciliation->missing_in_system],
                        ['Missing in Provider', $reconciliation->missing_in_provider],
                        ['Match Rate', $reconciliation->getMatchRate() . '%'],
                        ['Total Amount', number_format($reconciliation->total_amount, 2)],
                        ['Matched Amount', number_format($reconciliation->matched_amount, 2)],
                        ['Discrepancy', number_format($reconciliation->discrepancy_amount, 2)],
                    ]
                );

                if ($reconciliation->hasDiscrepancies()) {
                    $this->warn("  ⚠ Discrepancies found. Batch ID: {$reconciliation->batch_id}");
                } else {
                    $this->info("  ✓ No discrepancies found");
                }

                $this->newLine();
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
                $this->newLine();
            }
        }

        return 0;
    }

    protected function getProviders(): array
    {
        if ($this->option('all')) {
            return ['stripe', 'sslcommerz'];
        }

        if ($provider = $this->option('provider')) {
            return [$provider];
        }

        // Default to all providers
        return ['stripe', 'sslcommerz'];
    }
}
