<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotency Keys table
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('endpoint');
            $table->string('method', 10);
            $table->json('request_params')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->boolean('is_processing')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['key', 'expires_at']);
            $table->index(['user_id', 'endpoint']);
        });

        // Failed Payment Retry Queue
        Schema::create('payment_retries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payment_method'); // stripe, sslcommerz
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BDT');
            $table->json('payment_data')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'cancelled'])->default('pending');
            $table->text('last_error')->nullable();
            $table->string('last_error_code')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'next_retry_at']);
            $table->index(['booking_id', 'status']);
        });

        // Payment Reconciliation Records
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 36)->unique();
            $table->date('reconciliation_date');
            $table->string('payment_provider'); // stripe, sslcommerz
            $table->integer('total_transactions')->default(0);
            $table->integer('matched_transactions')->default(0);
            $table->integer('mismatched_transactions')->default(0);
            $table->integer('missing_in_system')->default(0);
            $table->integer('missing_in_provider')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('matched_amount', 12, 2)->default(0);
            $table->decimal('discrepancy_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->json('discrepancies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['reconciliation_date', 'payment_provider']);
            $table->index('status');
        });

        // Add idempotency_key to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('id')->unique();
            $table->string('provider_transaction_id')->nullable()->after('transaction_id');
            $table->json('provider_response')->nullable()->after('payment_method');
            $table->timestamp('reconciled_at')->nullable();
            $table->boolean('is_reconciled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'provider_transaction_id', 'provider_response', 'reconciled_at', 'is_reconciled']);
        });

        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('payment_retries');
        Schema::dropIfExists('idempotency_keys');
    }
};
