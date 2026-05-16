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

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'commuter_id',
        'trip_id',
        'passenger_start_id',
        'passenger_stop_id',
        'fare',
    ];

    protected $casts = [
        'fare' => 'decimal:2',
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
}
