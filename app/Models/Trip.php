<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'trips';

    protected $fillable = [
        'driver_id',
        'departure_time',
        'return_time',
        'start_terminal_id',
        'capacity',
        'status',
        'trip_start_waypoint_id',
        'trip_end_waypoint_id',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'return_time' => 'datetime',
        'capacity' => 'integer',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function startTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'start_terminal_id');
    }

    public function startWaypoint(): BelongsTo
    {
        return $this->belongsTo(Waypoint::class, 'trip_start_waypoint_id');
    }

    public function endWaypoint(): BelongsTo
    {
        return $this->belongsTo(Waypoint::class, 'trip_end_waypoint_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class, 'trip_id');
    }
}
