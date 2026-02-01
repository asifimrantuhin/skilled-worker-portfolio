<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentReconciliation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    /**
     * Run reconciliation for a specific date and provider
     */
    public function reconcile(Carbon $date, string $provider): PaymentReconciliation
    {
        $reconciliation = PaymentReconciliation::createForDate($date, $provider);
        $reconciliation->start();

        try {
            // Get payments from our system
            $systemPayments = $this->getSystemPayments($date, $provider);

            // Get payments from provider (this would call their API)
            $providerPayments = $this->getProviderPayments($date, $provider);

            // Build lookup maps
            $systemMap = $systemPayments->keyBy('transaction_id');
            $providerMap = collect($providerPayments)->keyBy('transaction_id');

            $matched = 0;
            $matchedAmount = 0;
            $mismatched = 0;
            $missingInSystem = 0;
            $missingInProvider = 0;
            $discrepancies = [];

            // Check each system payment against provider
            foreach ($systemPayments as $payment) {
                $providerRecord = $providerMap->get($payment->transaction_id);

                if (!$providerRecord) {
                    $missingInProvider++;
                    $discrepancies[] = [
                        'type' => 'missing_in_provider',
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'amount' => $payment->amount,
                    ];
                    continue;
                }

                // Check if amounts match
                if (abs($payment->amount - $providerRecord['amount']) > 0.01) {
                    $mismatched++;
                    $discrepancies[] = [
                        'type' => 'amount_mismatch',
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'system_amount' => $payment->amount,
                        'provider_amount' => $providerRecord['amount'],
                        'difference' => $payment->amount - $providerRecord['amount'],
                    ];
                } else {
                    $matched++;
                    $matchedAmount += $payment->amount;

                    // Mark payment as reconciled
                    $payment->update([
                        'is_reconciled' => true,
                        'reconciled_at' => now(),
                    ]);
                }
            }

            // Check for payments in provider but not in system
            foreach ($providerPayments as $providerPayment) {
                if (!$systemMap->has($providerPayment['transaction_id'])) {
                    $missingInSystem++;
                    $discrepancies[] = [
                        'type' => 'missing_in_system',
                        'transaction_id' => $providerPayment['transaction_id'],
                        'amount' => $providerPayment['amount'],
                        'provider_data' => $providerPayment,
                    ];
                }
            }

            // Calculate totals
            $totalAmount = $systemPayments->sum('amount');
            $discrepancyAmount = $totalAmount - $matchedAmount;

            // Update reconciliation record
            $reconciliation->update([
                'total_transactions' => $systemPayments->count(),
                'matched_transactions' => $matched,
                'mismatched_transactions' => $mismatched,
                'missing_in_system' => $missingInSystem,
                'missing_in_provider' => $missingInProvider,
                'total_amount' => $totalAmount,
                'matched_amount' => $matchedAmount,
                'discrepancy_amount' => $discrepancyAmount,
                'discrepancies' => $discrepancies,
            ]);

            $reconciliation->complete();

            return $reconciliation;
        } catch (\Exception $e) {
            Log::error('Reconciliation failed', [
                'date' => $date->toDateString(),
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $reconciliation->fail($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get payments from our system
     */
    protected function getSystemPayments(Carbon $date, string $provider)
    {
        return Payment::whereDate('created_at', $date)
            ->where('payment_method', $provider)
            ->where('status', 'completed')
            ->get();
    }

    /**
     * Get payments from provider API
     * This is a placeholder - implement actual API calls
     */
    protected function getProviderPayments(Carbon $date, string $provider): array
    {
        // In a real implementation, this would call the provider's API
        // For Stripe: use the Balance Transactions API
        // For SSLCommerz: use their Transaction Query API

        switch ($provider) {
            case 'stripe':
                return $this->getStripePayments($date);
            case 'sslcommerz':
                return $this->getSSLCommerzPayments($date);
            default:
                return [];
        }
    }

    protected function getStripePayments(Carbon $date): array
    {
        // Placeholder - implement Stripe API call
        // $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        // $transactions = $stripe->balanceTransactions->all([
        //     'created' => [
        //         'gte' => $date->startOfDay()->timestamp,
        //         'lte' => $date->endOfDay()->timestamp,
        //     ],
        //     'type' => 'charge',
        // ]);

        return [];
    }

    protected function getSSLCommerzPayments(Carbon $date): array
    {
        // Placeholder - implement SSLCommerz API call
        return [];
    }

    /**
     * Get reconciliation summary for a date range
     */
    public function getSummary(Carbon $from, Carbon $to, string $provider = null): array
    {
        $query = PaymentReconciliation::whereBetween('reconciliation_date', [$from, $to])
            ->completed();

        if ($provider) {
            $query->forProvider($provider);
        }

        $reconciliations = $query->get();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'total_reconciliations' => $reconciliations->count(),
            'total_transactions' => $reconciliations->sum('total_transactions'),
            'total_matched' => $reconciliations->sum('matched_transactions'),
            'total_discrepancies' => $reconciliations->sum('mismatched_transactions')
                + $reconciliations->sum('missing_in_system')
                + $reconciliations->sum('missing_in_provider'),
            'total_amount' => $reconciliations->sum('total_amount'),
            'matched_amount' => $reconciliations->sum('matched_amount'),
            'discrepancy_amount' => $reconciliations->sum('discrepancy_amount'),
            'match_rate' => $reconciliations->sum('total_transactions') > 0
                ? round(($reconciliations->sum('matched_transactions') / $reconciliations->sum('total_transactions')) * 100, 2)
                : 100,
        ];
    }
}
