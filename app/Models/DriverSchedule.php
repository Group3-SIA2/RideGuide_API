<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSchedule extends Model
{
    use HasUuids;

    protected $fillable = [
        'driver_assign_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    /**
     * The assignment this schedule belongs to.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DriverAssignTerminal::class, 'driver_assign_id');
    }
}
