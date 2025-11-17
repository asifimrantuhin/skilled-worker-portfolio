<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageAvailability extends Model
{
    use HasFactory;

    protected $table = 'package_availability';

    protected $fillable = [
        'package_id',
        'date',
        'available_slots',
        'booked_slots',
        'price_override',
        'is_available',
    ];

    protected $casts = [
        'date' => 'date',
        'price_override' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function getRemainingSlotsAttribute()
    {
        return $this->available_slots - $this->booked_slots;
    }
}

