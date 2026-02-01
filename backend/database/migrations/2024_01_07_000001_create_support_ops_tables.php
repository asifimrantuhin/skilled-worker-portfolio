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
        // Canned Responses for quick ticket replies
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('shortcut', 50)->unique(); // e.g., /refund, /thanks
            $table->text('content');
            $table->string('category')->nullable();
            $table->json('variables')->nullable(); // Placeholders like {customer_name}
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
        });

        // Escalation Rules
        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('trigger_type', ['time_based', 'priority_based', 'status_based', 'custom']);
            $table->json('conditions'); // Rule conditions
            $table->json('actions'); // What to do when triggered
            $table->enum('escalation_level', ['l1', 'l2', 'l3', 'manager'])->default('l2');
            $table->integer('time_threshold_hours')->nullable(); // For time-based rules
            $table->boolean('notify_customer')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Rule execution order
            $table->timestamps();
            
            $table->index(['trigger_type', 'is_active']);
        });

        // Ticket SLA Tracking
        Schema::create('ticket_slas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->string('sla_type'); // first_response, resolution
            $table->integer('target_hours');
            $table->timestamp('started_at');
            $table->timestamp('target_at');
            $table->timestamp('achieved_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->boolean('is_paused')->default(false);
            $table->integer('paused_duration_minutes')->default(0);
            $table->enum('status', ['active', 'achieved', 'breached', 'cancelled'])->default('active');
            $table->timestamps();
            
            $table->index(['ticket_id', 'sla_type']);
            $table->index(['status', 'target_at']);
        });

        // Satisfaction Surveys
        Schema::create('satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->morphs('surveyable'); // Can be linked to ticket, booking, etc.
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('survey_token', 64)->unique();
            $table->integer('rating')->nullable(); // 1-5 stars
            $table->text('feedback')->nullable();
            $table->json('custom_responses')->nullable(); // For multi-question surveys
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->timestamp('sent_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->index(['agent_id', 'rating']);
        });

        // Update tickets table with SLA and escalation fields
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('escalation_level')->nullable()->after('status');
            $table->timestamp('escalated_at')->nullable()->after('escalation_level');
            $table->foreignId('escalated_from')->nullable()->after('escalated_at')->constrained('users')->onDelete('set null');
            $table->timestamp('first_response_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->string('sla_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['escalated_from']);
            $table->dropColumn([
                'escalation_level',
                'escalated_at',
                'escalated_from',
                'first_response_at',
                'sla_breached',
                'sla_status',
            ]);
        });

        Schema::dropIfExists('satisfaction_surveys');
        Schema::dropIfExists('ticket_slas');
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('canned_responses');
    }
};
