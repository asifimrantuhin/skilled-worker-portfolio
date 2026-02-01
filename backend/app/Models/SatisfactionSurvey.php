<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'surveyable_type',
        'surveyable_id',
        'user_id',
        'survey_token',
        'rating',
        'feedback',
        'categories',
        'completed_at',
        'sent_at',
        'expires_at',
    ];

    protected $casts = [
        'categories' => 'array',
        'completed_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($survey) {
            if (!$survey->survey_token) {
                $survey->survey_token = Str::random(64);
            }
            if (!$survey->expires_at) {
                $survey->expires_at = now()->addDays(7);
            }
        });
    }

    // Relationships
    public function surveyable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('completed_at')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereNull('completed_at')
            ->where('expires_at', '<', now());
    }

    public function scopeForModel($query, Model $model)
    {
        return $query->where('surveyable_type', get_class($model))
            ->where('surveyable_id', $model->id);
    }

    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopePositive($query)
    {
        return $query->where('rating', '>=', 4);
    }

    public function scopeNegative($query)
    {
        return $query->where('rating', '<=', 2);
    }

    public function scopeNeutral($query)
    {
        return $query->where('rating', 3);
    }

    // Helper Methods
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    public function isExpired(): bool
    {
        return !$this->isCompleted() && $this->expires_at < now();
    }

    public function isPending(): bool
    {
        return !$this->isCompleted() && !$this->isExpired();
    }

    public function complete(int $rating, ?string $feedback = null, ?array $categories = null): void
    {
        $this->update([
            'rating' => $rating,
            'feedback' => $feedback,
            'categories' => $categories,
            'completed_at' => now(),
        ]);
    }

    public function getSatisfactionLevel(): string
    {
        if (!$this->rating) {
            return 'pending';
        }

        return match(true) {
            $this->rating >= 4 => 'satisfied',
            $this->rating === 3 => 'neutral',
            default => 'dissatisfied',
        };
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('survey_token', $token)->first();
    }

    public static function createForTicket(Ticket $ticket): self
    {
        return self::create([
            'surveyable_type' => Ticket::class,
            'surveyable_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'sent_at' => now(),
        ]);
    }

    // Statistics Methods
    public static function averageRating(?string $surveyableType = null): float
    {
        $query = self::completed();
        
        if ($surveyableType) {
            $query->where('surveyable_type', $surveyableType);
        }

        return round($query->avg('rating') ?? 0, 2);
    }

    public static function satisfactionRate(?string $surveyableType = null): float
    {
        $query = self::completed();
        
        if ($surveyableType) {
            $query->where('surveyable_type', $surveyableType);
        }

        $total = $query->count();
        if ($total === 0) {
            return 0;
        }

        $satisfied = (clone $query)->where('rating', '>=', 4)->count();

        return round(($satisfied / $total) * 100, 2);
    }

    public static function responseRate(?string $surveyableType = null): float
    {
        $baseQuery = self::query();
        
        if ($surveyableType) {
            $baseQuery->where('surveyable_type', $surveyableType);
        }

        $total = (clone $baseQuery)->count();
        if ($total === 0) {
            return 0;
        }

        $completed = (clone $baseQuery)->completed()->count();

        return round(($completed / $total) * 100, 2);
    }

    public static function ratingDistribution(?string $surveyableType = null): array
    {
        $query = self::completed();
        
        if ($surveyableType) {
            $query->where('surveyable_type', $surveyableType);
        }

        $distribution = $query
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Ensure all ratings are represented
        $result = [];
        for ($i = 1; $i <= 5; $i++) {
            $result[$i] = $distribution[$i] ?? 0;
        }

        return $result;
    }
}
