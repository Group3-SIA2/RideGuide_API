<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TripPassenger extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'trip_passengers';

    protected $fillable = [
        'commuter_id',
        'trip_id',
        'passenger_start_id',
        'passenger_stop_id',
        'destination_terminal_id',
        'destination_latitude',
        'destination_longitude',
        'status',
        'joined_at',
        'picked_up_at',
        'dropped_off_at',
        'fare',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'dropped_off_at' => 'datetime',
        'destination_latitude' => 'float',
        'destination_longitude' => 'float',
        'fare' => 'float',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function commuter(): BelongsTo
    {
        return $this->belongsTo(Commuter::class, 'commuter_id');
    }

    public function passengerStart(): BelongsTo
    {
        return $this->belongsTo(PassengerStart::class, 'passenger_start_id');
    }

    public function passengerStop(): BelongsTo
    {
        return $this->belongsTo(PassengerStop::class, 'passenger_stop_id');
    }

    public function destinationTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'destination_terminal_id');
    }
}
