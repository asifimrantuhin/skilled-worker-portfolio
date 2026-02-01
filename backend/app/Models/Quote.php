<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Quote extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'quote_number',
        'title',
        'description',
        'items',
        'subtotal',
        'discount',
        'tax',
        'total',
        'status',
        'valid_until',
        'terms_conditions',
        'internal_notes',
        'sent_at',
        'viewed_at',
        'responded_at',
        'converted_booking_id',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (empty($quote->quote_number)) {
                $quote->quote_number = 'QT-' . strtoupper(Str::random(8));
            }
        });
    }

    // Relationships
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function convertedBooking()
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function reminders()
    {
        return $this->morphMany(FollowUpReminder::class, 'remindable');
    }

    // Scopes
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'viewed']);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', '!=', 'expired')
            ->where('valid_until', '<', now());
    }

    // Helper Methods
    public function isExpired(): bool
    {
        return $this->valid_until < now() && !in_array($this->status, ['accepted', 'declined']);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->update([
                'status' => 'viewed',
                'viewed_at' => now(),
            ]);
        }
    }

    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    public function decline(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    public function calculateTotals(): void
    {
        $subtotal = 0;
        foreach ($this->items as $item) {
            $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        $this->subtotal = $subtotal;
        $this->total = $subtotal - $this->discount + $this->tax;
    }
}
