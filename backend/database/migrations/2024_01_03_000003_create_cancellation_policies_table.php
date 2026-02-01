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
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cancellation_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancellation_policy_id')->constrained()->cascadeOnDelete();
            $table->integer('days_before_travel')->comment('Cancel X days before travel');
            $table->decimal('refund_percentage', 5, 2)->comment('Refund % of paid amount');
            $table->decimal('fee_amount', 10, 2)->default(0)->comment('Fixed fee for cancellation');
            $table->timestamps();

            $table->index(['cancellation_policy_id', 'days_before_travel']);
        });

        // Add cancellation policy to packages
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('cancellation_policy_id')->nullable()->after('is_featured')->constrained()->nullOnDelete();
        });

        // Add promo code and cancellation fee to bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('special_requests')->constrained()->nullOnDelete();
            $table->decimal('promo_discount', 10, 2)->default(0)->after('promo_code_id');
            $table->decimal('cancellation_fee', 10, 2)->default(0)->after('promo_discount');
            $table->decimal('refund_amount', 10, 2)->default(0)->after('cancellation_fee');
            $table->string('hold_token', 64)->nullable()->after('refund_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['promo_code_id', 'promo_discount', 'cancellation_fee', 'refund_amount', 'hold_token']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropForeign(['cancellation_policy_id']);
            $table->dropColumn('cancellation_policy_id');
        });

        Schema::dropIfExists('cancellation_policy_rules');
        Schema::dropIfExists('cancellation_policies');
    }
};
