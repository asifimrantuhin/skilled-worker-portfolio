<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function rules()
    {
        return $this->hasMany(CancellationPolicyRule::class)->orderBy('days_before_travel', 'desc');
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function getApplicableRule(int $daysBeforeTravel): ?CancellationPolicyRule
    {
        return $this->rules()
            ->where('days_before_travel', '<=', $daysBeforeTravel)
            ->orderBy('days_before_travel', 'desc')
            ->first();
    }

    public function calculateRefund(float $paidAmount, int $daysBeforeTravel): array
    {
        $rule = $this->getApplicableRule($daysBeforeTravel);

        if (! $rule) {
            return [
                'refund_percentage' => 0,
                'refund_amount' => 0,
                'cancellation_fee' => $paidAmount,
                'rule_applied' => null,
            ];
        }

        $refundAmount = ($paidAmount * $rule->refund_percentage / 100) - $rule->fee_amount;
        $refundAmount = max(0, $refundAmount);
        $cancellationFee = $paidAmount - $refundAmount;

        return [
            'refund_percentage' => $rule->refund_percentage,
            'refund_amount' => round($refundAmount, 2),
            'cancellation_fee' => round($cancellationFee, 2),
            'rule_applied' => $rule,
        ];
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }
}
