<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TripWaypoint;

class Trip extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'trips';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'driver_id',
        'departure_time',
        'return_time',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'return_time'    => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class, 'trip_id');
    }

    public function waypoints(): HasMany
    {
        return $this->hasMany(TripWaypoint::class, 'trip_id')->orderBy('sequence');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'trip_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('return_time');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('return_time');
    }
}
