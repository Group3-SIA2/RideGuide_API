<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PassengerStop extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'passenger_stops';

    protected $fillable = [
        'waypoint_id',
    ];

    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(Waypoint::class, 'waypoint_id');
    }

    public function tripPassengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class, 'passenger_stop_id');
    }
}
