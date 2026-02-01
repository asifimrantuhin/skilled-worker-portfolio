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
        // API Request Logs
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('full_url', 2000)->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->integer('status_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->integer('response_size')->nullable();
            $table->decimal('duration_ms', 10, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('controller')->nullable();
            $table->string('action')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['path', 'method', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index('created_at');
        });

        // Performance Metrics
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name');
            $table->string('metric_type'); // counter, gauge, histogram
            $table->decimal('value', 15, 4);
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            
            $table->index(['metric_name', 'recorded_at']);
            $table->index('recorded_at');
        });

        // Error Logs
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_hash', 64)->index(); // For grouping similar errors
            $table->string('request_id', 36)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('exception_class');
            $table->string('message', 1000);
            $table->text('stack_trace')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->string('severity')->default('error'); // debug, info, warning, error, critical
            $table->json('context')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->integer('occurrence_count')->default(1);
            $table->timestamp('first_occurred_at');
            $table->timestamp('last_occurred_at');
            $table->timestamps();
            
            $table->index(['exception_class', 'is_resolved']);
            $table->index(['severity', 'created_at']);
            $table->index('created_at');
        });

        // Health Check Results
        Schema::create('health_check_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_name');
            $table->enum('status', ['healthy', 'degraded', 'unhealthy']);
            $table->decimal('response_time_ms', 10, 2)->nullable();
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            
            $table->index(['check_name', 'checked_at']);
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_check_results');
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('performance_metrics');
        Schema::dropIfExists('api_logs');
    }
};
