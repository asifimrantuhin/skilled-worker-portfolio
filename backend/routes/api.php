<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\InquiryController;
use App\Http\Controllers\API\TicketController;
use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\PromoCodeController;
use App\Http\Controllers\API\CancellationPolicyController;
use App\Http\Controllers\API\QuoteController;
use App\Http\Controllers\API\FollowUpReminderController;
use App\Http\Controllers\API\ReferralController;
use App\Http\Controllers\API\CommissionTierController;
use App\Http\Controllers\API\HealthController;
use App\Http\Controllers\API\MetricsController;
use App\Http\Controllers\CannedResponseController;
use App\Http\Controllers\EscalationRuleController;
use App\Http\Controllers\SatisfactionSurveyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/password/forgot', [AuthController::class, 'requestPasswordReset']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');

// Public quote viewing
Route::get('/quotes/view/{quoteNumber}', [QuoteController::class, 'viewQuote']);
Route::post('/quotes/respond/{quoteNumber}', [QuoteController::class, 'respondToQuote']);

// Public referral code validation
Route::post('/referrals/validate-code', [ReferralController::class, 'validateCode']);

// Public satisfaction survey endpoints
Route::prefix('surveys')->group(function () {
    Route::get('/{token}', [SatisfactionSurveyController::class, 'showByToken']);
    Route::post('/{token}', [SatisfactionSurveyController::class, 'submit']);
});

// Health check endpoints (public for monitoring)
Route::prefix('health')->group(function () {
    Route::get('/', [HealthController::class, 'health']);
    Route::get('/live', [HealthController::class, 'liveness']);
    Route::get('/ready', [HealthController::class, 'readiness']);
    Route::get('/check/{checkName}', [HealthController::class, 'check']);
});

// Package routes (public)
Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/{id}', [PackageController::class, 'show']);
Route::get('/packages/{id}/availability', [PackageController::class, 'checkAvailability']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Package management (admin/agent)
    Route::middleware('role:admin,agent')->group(function () {
        Route::post('/packages', [PackageController::class, 'store']);
        Route::put('/packages/{id}', [PackageController::class, 'update']);
        Route::delete('/packages/{id}', [PackageController::class, 'destroy']);
        Route::post('/packages/{id}/availability', [PackageController::class, 'setAvailability']);
    });

    // Booking routes
    Route::prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index']);
        Route::post('/', [BookingController::class, 'store']);
        Route::get('/{id}', [BookingController::class, 'show']);
        Route::put('/{id}/cancel', [BookingController::class, 'cancel']);
        Route::get('/{id}/cancellation-preview', [BookingController::class, 'previewCancellation']);
        
        // Inventory hold management
        Route::post('/hold', [BookingController::class, 'holdInventory']);
        Route::post('/hold/release', [BookingController::class, 'releaseHold']);
        
        // Promo code validation
        Route::post('/validate-promo', [BookingController::class, 'validatePromoCode']);
        
        // Admin/Agent only
        Route::middleware('role:admin,agent')->group(function () {
            Route::put('/{id}/confirm', [BookingController::class, 'confirm']);
            Route::put('/{id}/status', [BookingController::class, 'updateStatus']);
        });
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/stripe', [PaymentController::class, 'stripePayment']);
        Route::post('/sslcommerz', [PaymentController::class, 'sslcommerzPayment']);
        Route::post('/{id}/verify', [PaymentController::class, 'verifyPayment']);
        Route::get('/{id}', [PaymentController::class, 'show']);
    });

    // Inquiry routes
    Route::prefix('inquiries')->group(function () {
        Route::get('/', [InquiryController::class, 'index']);
        Route::post('/', [InquiryController::class, 'store']);
        Route::get('/{id}', [InquiryController::class, 'show']);
        Route::put('/{id}', [InquiryController::class, 'update']);
        
        // Admin/Agent only
        Route::middleware('role:admin,agent')->group(function () {
            Route::put('/{id}/assign', [InquiryController::class, 'assign']);
            Route::put('/{id}/status', [InquiryController::class, 'updateStatus']);
        });
    });

    // Ticket routes
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::post('/', [TicketController::class, 'store']);
        Route::get('/{id}', [TicketController::class, 'show']);
        Route::put('/{id}', [TicketController::class, 'update']);
        Route::post('/{id}/replies', [TicketController::class, 'addReply']);
        
        // Admin/Agent only
        Route::middleware('role:admin,agent')->group(function () {
            Route::put('/{id}/assign', [TicketController::class, 'assign']);
            Route::put('/{id}/status', [TicketController::class, 'updateStatus']);
            Route::put('/{id}/resolve', [TicketController::class, 'resolve']);
            Route::put('/{id}/escalate', [TicketController::class, 'escalate']);
            Route::put('/{id}/deescalate', [TicketController::class, 'deescalate']);
            Route::get('/{id}/sla', [TicketController::class, 'getSlaStatus']);
            Route::post('/{id}/survey', [SatisfactionSurveyController::class, 'sendForTicket']);
        });
    });
    
    // Canned Responses (accessible by agents and admins)
    Route::middleware('role:admin,agent')->prefix('canned-responses')->group(function () {
        Route::get('/', [CannedResponseController::class, 'index']);
        Route::get('/categories', [CannedResponseController::class, 'categories']);
        Route::get('/shortcut/{shortcut}', [CannedResponseController::class, 'findByShortcut']);
        Route::get('/{cannedResponse}', [CannedResponseController::class, 'show']);
        Route::get('/{cannedResponse}/preview', [CannedResponseController::class, 'preview']);
    });

    // Agent panel routes
    Route::middleware('role:agent')->prefix('agent')->group(function () {
        Route::get('/dashboard', [AgentController::class, 'dashboard']);
        Route::get('/bookings', [AgentController::class, 'bookings']);
        Route::get('/commissions', [AgentController::class, 'commissions']);
        Route::get('/customers', [AgentController::class, 'customers']);
        
        // Quote Builder
        Route::prefix('quotes')->group(function () {
            Route::get('/', [QuoteController::class, 'index']);
            Route::post('/', [QuoteController::class, 'store']);
            Route::get('/{id}', [QuoteController::class, 'show']);
            Route::put('/{id}', [QuoteController::class, 'update']);
            Route::delete('/{id}', [QuoteController::class, 'destroy']);
            Route::post('/{id}/send', [QuoteController::class, 'send']);
            Route::post('/{id}/duplicate', [QuoteController::class, 'duplicate']);
        });
        
        // Follow-up Reminders
        Route::prefix('reminders')->group(function () {
            Route::get('/', [FollowUpReminderController::class, 'index']);
            Route::get('/dashboard', [FollowUpReminderController::class, 'dashboard']);
            Route::post('/', [FollowUpReminderController::class, 'store']);
            Route::get('/{id}', [FollowUpReminderController::class, 'show']);
            Route::put('/{id}', [FollowUpReminderController::class, 'update']);
            Route::put('/{id}/complete', [FollowUpReminderController::class, 'complete']);
            Route::put('/{id}/snooze', [FollowUpReminderController::class, 'snooze']);
            Route::put('/{id}/reschedule', [FollowUpReminderController::class, 'reschedule']);
            Route::put('/{id}/cancel', [FollowUpReminderController::class, 'cancel']);
            Route::post('/bulk-complete', [FollowUpReminderController::class, 'bulkComplete']);
        });
        
        // Referrals
        Route::prefix('referrals')->group(function () {
            Route::get('/', [ReferralController::class, 'index']);
            Route::post('/', [ReferralController::class, 'create']);
            Route::get('/my-code', [ReferralController::class, 'getMyReferralCode']);
            Route::get('/{id}', [ReferralController::class, 'show']);
            Route::post('/{id}/resend', [ReferralController::class, 'resendInvitation']);
            Route::put('/{id}/cancel', [ReferralController::class, 'cancel']);
        });
    });

    // Admin only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats', [DashboardController::class, 'adminStats']);
        Route::get('/users', [AuthController::class, 'listUsers']);
        Route::put('/users/{id}/status', [AuthController::class, 'updateUserStatus']);
        
        // Promo code management
        Route::prefix('promo-codes')->group(function () {
            Route::get('/', [PromoCodeController::class, 'index']);
            Route::post('/', [PromoCodeController::class, 'store']);
            Route::get('/{id}', [PromoCodeController::class, 'show']);
            Route::put('/{id}', [PromoCodeController::class, 'update']);
            Route::delete('/{id}', [PromoCodeController::class, 'destroy']);
            Route::put('/{id}/toggle-status', [PromoCodeController::class, 'toggleStatus']);
            Route::get('/{id}/usage-report', [PromoCodeController::class, 'usageReport']);
        });
        
        // Cancellation policy management
        Route::prefix('cancellation-policies')->group(function () {
            Route::get('/', [CancellationPolicyController::class, 'index']);
            Route::post('/', [CancellationPolicyController::class, 'store']);
            Route::get('/{id}', [CancellationPolicyController::class, 'show']);
            Route::put('/{id}', [CancellationPolicyController::class, 'update']);
            Route::delete('/{id}', [CancellationPolicyController::class, 'destroy']);
            Route::put('/{id}/set-default', [CancellationPolicyController::class, 'setDefault']);
        });
        
        // Commission Tier management
        Route::prefix('commission-tiers')->group(function () {
            Route::get('/', [CommissionTierController::class, 'index']);
            Route::post('/', [CommissionTierController::class, 'store']);
            Route::get('/{id}', [CommissionTierController::class, 'show']);
            Route::put('/{id}', [CommissionTierController::class, 'update']);
            Route::delete('/{id}', [CommissionTierController::class, 'destroy']);
            Route::post('/assign-agent', [CommissionTierController::class, 'assignAgent']);
            Route::get('/agent/{agentId}/history', [CommissionTierController::class, 'agentHistory']);
            Route::post('/evaluate-agents', [CommissionTierController::class, 'evaluateAgents']);
        });
        
        // Referral rewards processing
        Route::post('/referrals/process-rewards', [ReferralController::class, 'processRewards']);
        
        // Observability & Metrics
        Route::prefix('metrics')->group(function () {
            Route::get('/dashboard', [MetricsController::class, 'dashboard']);
            Route::get('/realtime', [MetricsController::class, 'realtime']);
            Route::get('/api-logs', [MetricsController::class, 'apiLogs']);
            Route::get('/errors', [MetricsController::class, 'errorLogs']);
            Route::get('/errors/{id}', [MetricsController::class, 'errorDetail']);
            Route::put('/errors/{id}/resolve', [MetricsController::class, 'resolveError']);
            Route::post('/errors/bulk-resolve', [MetricsController::class, 'bulkResolveErrors']);
            Route::get('/performance', [MetricsController::class, 'performanceMetrics']);
            Route::get('/health-history', [MetricsController::class, 'healthHistory']);
        });
        
        // Support Ops - Canned Responses Management
        Route::prefix('canned-responses')->group(function () {
            Route::post('/', [CannedResponseController::class, 'store']);
            Route::put('/{cannedResponse}', [CannedResponseController::class, 'update']);
            Route::delete('/{cannedResponse}', [CannedResponseController::class, 'destroy']);
            Route::put('/{cannedResponse}/toggle-active', [CannedResponseController::class, 'toggleActive']);
            Route::get('/statistics', [CannedResponseController::class, 'statistics']);
        });
        
        // Support Ops - Escalation Rules Management
        Route::prefix('escalation-rules')->group(function () {
            Route::get('/', [EscalationRuleController::class, 'index']);
            Route::get('/options', [EscalationRuleController::class, 'options']);
            Route::post('/', [EscalationRuleController::class, 'store']);
            Route::get('/{escalationRule}', [EscalationRuleController::class, 'show']);
            Route::put('/{escalationRule}', [EscalationRuleController::class, 'update']);
            Route::delete('/{escalationRule}', [EscalationRuleController::class, 'destroy']);
            Route::put('/{escalationRule}/toggle-active', [EscalationRuleController::class, 'toggleActive']);
            Route::post('/{escalationRule}/test', [EscalationRuleController::class, 'testRule']);
            Route::post('/reorder', [EscalationRuleController::class, 'reorder']);
        });
        
        // Support Ops - Satisfaction Surveys
        Route::prefix('surveys')->group(function () {
            Route::get('/', [SatisfactionSurveyController::class, 'index']);
            Route::get('/statistics', [SatisfactionSurveyController::class, 'statistics']);
            Route::get('/categories', [SatisfactionSurveyController::class, 'categorySummary']);
            Route::get('/{satisfactionSurvey}', [SatisfactionSurveyController::class, 'show']);
            Route::post('/{satisfactionSurvey}/resend', [SatisfactionSurveyController::class, 'resend']);
        });
    });
});

