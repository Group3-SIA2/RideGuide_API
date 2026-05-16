<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommuterRideRequest extends Model
{
    use HasUuids;
    use SoftDeletes;
    use HasFactory;

    protected $table = 'commuter_ride_requests';

    protected $fillable = [
        'commuter_id',
        'route_id',
        'terminal_id',
        'destination',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at'        => 'datetime',
        'pickup_latitude'   => 'decimal:7',
        'pickup_longitude'  => 'decimal:7',
        'dropoff_latitude'  => 'decimal:7',
        'dropoff_longitude' => 'decimal:7',
    ];

    /**
     * The Commuter (User) who made this request
     */
    public function commuter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commuter_id');
    }

    /**
     * The Route this request is associated with
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class, 'route_id');
    }

    /**
     * The Terminal this request starts from
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * Driver responses to this commuter request
     */
    public function rideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'commuter_ride_request_id');
    }

    /**
     * Scope: Active requests that haven't expired
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Expired requests
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: Not expired requests
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
