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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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
        });
    });

    // Agent panel routes
    Route::middleware('role:agent')->prefix('agent')->group(function () {
        Route::get('/dashboard', [AgentController::class, 'dashboard']);
        Route::get('/bookings', [AgentController::class, 'bookings']);
        Route::get('/commissions', [AgentController::class, 'commissions']);
        Route::get('/customers', [AgentController::class, 'customers']);
    });

    // Admin only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats', [DashboardController::class, 'adminStats']);
        Route::get('/users', [AuthController::class, 'listUsers']);
        Route::put('/users/{id}/status', [AuthController::class, 'updateUserStatus']);
    });
});

