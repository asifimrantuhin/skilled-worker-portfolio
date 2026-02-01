<?php

namespace App\Console\Commands;

use App\Models\PaymentRetry;
use App\Services\SSLCommerzService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPaymentRetries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:retry {--limit=50 : Maximum number of retries to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending payment retries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $retries = PaymentRetry::getRetryQueue($limit);

        if ($retries->isEmpty()) {
            $this->info('No payment retries to process.');
            return 0;
        }

        $this->info("Processing {$retries->count()} payment retry(ies)...");

        $succeeded = 0;
        $failed = 0;

        foreach ($retries as $retry) {
            $this->line("Processing retry #{$retry->id} (Payment #{$retry->payment_id})...");

            $retry->markAsProcessing();

            try {
                $result = $this->processRetry($retry);

                if ($result['success']) {
                    $retry->markAsSucceeded();
                    $succeeded++;
                    $this->info("  ✓ Payment retry succeeded");

                    // Update the original payment
                    $retry->payment->update([
                        'status' => 'completed',
                        'transaction_id' => $result['transaction_id'] ?? $retry->payment->transaction_id,
                    ]);

                    // Update booking payment status
                    $retry->booking->update(['payment_status' => 'paid']);
                } else {
                    $retry->markAsFailed($result['error'], $result['error_code'] ?? null);
                    $failed++;

                    if ($retry->retry_count >= $retry->max_retries) {
                        $this->error("  ✗ Payment failed permanently after {$retry->max_retries} attempts");
                    } else {
                        $this->warn("  ↻ Payment failed, scheduled for retry #{$retry->retry_count}");
                    }
                }
            } catch (\Exception $e) {
                Log::error('Payment retry exception', [
                    'retry_id' => $retry->id,
                    'error' => $e->getMessage(),
                ]);

                $retry->markAsFailed($e->getMessage());
                $failed++;
                $this->error("  ✗ Exception: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Completed: {$succeeded} succeeded, {$failed} failed");

        return 0;
    }

    protected function processRetry(PaymentRetry $retry): array
    {
        switch ($retry->payment_method) {
            case 'stripe':
                return $this->processStripeRetry($retry);
            case 'sslcommerz':
                return $this->processSSLCommerzRetry($retry);
            default:
                return [
                    'success' => false,
                    'error' => 'Unknown payment method: ' . $retry->payment_method,
                ];
        }
    }

    protected function processStripeRetry(PaymentRetry $retry): array
    {
        // In a real implementation, use the Stripe SDK
        // $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        // try {
        //     $paymentIntent = $stripe->paymentIntents->confirm(
        //         $retry->payment_data['payment_intent_id']
        //     );
        //     return ['success' => true, 'transaction_id' => $paymentIntent->id];
        // } catch (\Stripe\Exception\CardException $e) {
        //     return ['success' => false, 'error' => $e->getMessage(), 'error_code' => $e->getStripeCode()];
        // }

        return [
            'success' => false,
            'error' => 'Stripe retry not implemented',
        ];
    }

    protected function processSSLCommerzRetry(PaymentRetry $retry): array
    {
        // In a real implementation, use the SSLCommerz SDK
        // $sslcommerz = app(SSLCommerzService::class);
        // return $sslcommerz->retryPayment($retry);

        return [
            'success' => false,
            'error' => 'SSLCommerz retry not implemented',
        ];
    }
}
