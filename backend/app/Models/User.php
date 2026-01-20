<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'avatar',
        'is_active',
        'commission_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    // Relationships
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function agentBookings()
    {
        return $this->hasMany(Booking::class, 'agent_id');
    }

    public function commissions()
    {
        return $this->hasMany(AgentCommission::class, 'agent_id');
    }

    public function inquiries()
    {
        return $this->hasMany(Inquiry::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function ticketReplies()
    {
        return $this->hasMany(TicketReply::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }
}
