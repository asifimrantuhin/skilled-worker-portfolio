<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'short_description',
        'slug',
        'duration_days',
        'duration_nights',
        'price',
        'discount_price',
        'destination',
        'category',
        'images',
        'itinerary',
        'inclusions',
        'exclusions',
        'max_participants',
        'min_participants',
        'is_active',
        'is_featured',
        'views',
        'bookings_count',
        'created_by',
    ];

    protected $casts = [
        'images' => 'array',
        'itinerary' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function availability()
    {
        return $this->hasMany(PackageAvailability::class);
    }

    public function inquiries()
    {
        return $this->hasMany(Inquiry::class);
    }
}

