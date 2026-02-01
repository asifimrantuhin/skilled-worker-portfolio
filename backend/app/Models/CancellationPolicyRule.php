<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationPolicyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'cancellation_policy_id',
        'days_before_travel',
        'refund_percentage',
        'fee_amount',
    ];

    protected $casts = [
        'refund_percentage' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    public function policy()
    {
        return $this->belongsTo(CancellationPolicy::class, 'cancellation_policy_id');
    }
}
