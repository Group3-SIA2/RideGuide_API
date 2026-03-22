<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverAssignTerminal extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'drv_assign_term';

    protected $fillable = [
        'driver_id',
        'terminal_id',
    ];

    /**
     * Assigned driver relationship.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    /**
     * Target terminal relationship.
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * Generated schedules for this assignment.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(DriverSchedule::class, 'driver_assign_id');
    }
}
