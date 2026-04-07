<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Waypoint extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'waypoint';

    protected $fillable = [
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function passengerStarts(): HasMany
    {
        return $this->hasMany(PassengerStart::class, 'waypoint_id');
    }

    public function passengerStops(): HasMany
    {
        return $this->hasMany(PassengerStop::class, 'waypoint_id');
    }

    public function tripStarts(): HasMany
    {
        return $this->hasMany(Trip::class, 'trip_start_waypoint_id');
    }

    public function tripEnds(): HasMany
    {
        return $this->hasMany(Trip::class, 'trip_end_waypoint_id');
    }
}
