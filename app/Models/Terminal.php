<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Terminal extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'terminal_name',
        'barangay',
        'city',
        'latitude',
        'longitude',
    ];

    /**
     * Get the driver assignments associated with the terminal.
     */
    public function driverAssignments(): HasMany
    {
        return $this->hasMany(DriverAssignTerminal::class, 'terminal_id');
    }

    public function organizationTerminals(): HasMany
    {
        return $this->hasMany(OrganizationTerminal::class, 'terminal_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_terminals', 'terminal_id', 'organization_id')
            ->withTimestamps()
            ->withPivot(['id', 'deleted_at'])
            ->wherePivotNull('deleted_at');
    }
}
