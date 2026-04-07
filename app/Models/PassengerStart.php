<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PassengerStart extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'passenger_start';

    protected $fillable = [
        'waypoint_id',
    ];

    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(Waypoint::class, 'waypoint_id');
    }

    public function tripPassengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class, 'passenger_start_id');
    }
}
