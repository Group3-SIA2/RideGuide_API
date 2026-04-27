<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideRequest extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'ride_requests';

    protected $fillable = [
        'driver_id',
        'commuter_ride_request_id',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /**
     * The Driver (User) responding to this request
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * The Commuter Ride Request this is responding to
     */
    public function commuterRideRequest(): BelongsTo
    {
        return $this->belongsTo(CommuterRideRequest::class, 'commuter_ride_request_id');
    }

    /**
     * Scope: Pending ride requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Accepted ride requests
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope: Completed ride requests
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
