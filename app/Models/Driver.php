<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Driver extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'driver';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'organization_id',
        'driver_license_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(\App\Models\Organization::class, 'organization_id');
    }

    public function usersEmergencyContact()
    {
        return $this->hasOne(\App\Models\UsersEmergencyContact::class, 'user_id', 'user_id');
    }

    public function licenseId()
    {
        return $this->belongsTo(LicenseId::class, 'driver_license_id');
    }

    public function getVerificationStatusAttribute(): ?string
    {
        $license = $this->getRelationValue('licenseId');

        if (! $license && $this->driver_license_id) {
            $license = $this->licenseId()->first();

            if ($license) {
                $this->setRelation('licenseId', $license);
            }
        }

        return $license?->verification_status;
    }

    public function getRejectionReasonAttribute(): ?string
    {
        $license = $this->getRelationValue('licenseId');

        if (! $license && $this->driver_license_id) {
            $license = $this->licenseId()->first();

            if ($license) {
                $this->setRelation('licenseId', $license);
            }
        }

        return $license?->rejection_reason;
    }

    /**
     * Driver's terminal assignments.
     */
    public function terminalAssignments(): HasMany
    {
        return $this->hasMany(DriverAssignTerminal::class, 'driver_id');
    }

    /**
     * Terminals associated with the driver.
     */
    public function terminals(): BelongsToMany
    {
        return $this->belongsToMany(Terminal::class, 'drv_assign_term', 'driver_id', 'terminal_id')
            ->withTimestamps()
            ->withPivot('id');
    }

    /**
     * Schedules linked through assignments.
     */
    public function schedules(): HasManyThrough
    {
        return $this->hasManyThrough(
            DriverSchedule::class,
            DriverAssignTerminal::class,
            'driver_id',
            'driver_assign_id'
        );
    }
}
