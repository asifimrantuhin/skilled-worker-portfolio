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
        // Quote Builder - Quotes table
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('quote_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('items'); // Array of package configs, custom items
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['draft', 'sent', 'viewed', 'accepted', 'declined', 'expired'])->default('draft');
            $table->date('valid_until');
            $table->text('terms_conditions')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('converted_booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['agent_id', 'status']);
            $table->index(['customer_email', 'status']);
        });

        // Customer Follow-up Reminders
        Schema::create('follow_up_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('customer_name')->nullable();
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->enum('reminder_type', ['inquiry', 'quote', 'booking', 'post_trip', 'custom']);
            $table->morphs('remindable'); // Can link to inquiry, quote, booking
            $table->string('title');
            $table->text('notes')->nullable();
            $table->datetime('remind_at');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['pending', 'completed', 'snoozed', 'cancelled'])->default('pending');
            $table->datetime('completed_at')->nullable();
            $table->datetime('snoozed_until')->nullable();
            $table->integer('snooze_count')->default(0);
            $table->timestamps();
            
            $table->index(['agent_id', 'status', 'remind_at']);
            $table->index(['remind_at', 'status']);
        });

        // Commission Tiers
        Schema::create('commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('min_bookings', 10, 2)->default(0); // Monthly threshold
            $table->decimal('min_revenue', 10, 2)->default(0); // Monthly threshold
            $table->decimal('commission_rate', 5, 2); // Percentage
            $table->decimal('bonus_rate', 5, 2)->default(0); // Additional bonus
            $table->json('benefits')->nullable(); // Additional perks
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Agent Tier History
        Schema::create('agent_tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('commission_tier_id')->constrained()->onDelete('cascade');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->integer('monthly_bookings')->default(0);
            $table->decimal('monthly_revenue', 10, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
            
            $table->index(['agent_id', 'effective_from']);
        });

        // Referral Tracking
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('referred_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('referral_code', 50)->unique();
            $table->string('referred_email');
            $table->string('referred_name')->nullable();
            $table->enum('status', ['pending', 'registered', 'booked', 'rewarded', 'expired'])->default('pending');
            $table->decimal('reward_amount', 10, 2)->nullable();
            $table->enum('reward_type', ['cash', 'credit', 'discount'])->default('cash');
            $table->boolean('reward_paid')->default(false);
            $table->datetime('registered_at')->nullable();
            $table->datetime('first_booking_at')->nullable();
            $table->datetime('reward_paid_at')->nullable();
            $table->datetime('expires_at');
            $table->timestamps();
            
            $table->index(['referrer_id', 'status']);
            $table->index('referral_code');
        });

        // Add commission_tier_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('commission_tier_id')->nullable()->after('commission_rate')->constrained()->onDelete('set null');
            $table->string('referral_code', 50)->nullable()->after('commission_tier_id')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['commission_tier_id']);
            $table->dropColumn(['commission_tier_id', 'referral_code']);
        });

        Schema::dropIfExists('referrals');
        Schema::dropIfExists('agent_tier_histories');
        Schema::dropIfExists('commission_tiers');
        Schema::dropIfExists('follow_up_reminders');
        Schema::dropIfExists('quotes');
    }
};
