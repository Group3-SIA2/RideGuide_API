<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DriverOrganizationAssignmentLog extends Model
{
    use HasUuids;

    public const ACTION_ASSIGN = 'assign';
    public const ACTION_REASSIGN = 'reassign';
    public const ACTION_UNASSIGN = 'unassign';

    protected $fillable = [
        'driver_id',
        'old_organization_id',
        'new_organization_id',
        'acted_by_user_id',
        'action',
    ];
}
