<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use app\Models\User;

class Driver extends Model
{
    use HasUuids, SoftDeletes;

    const VERIFICATION_STATUS_UNVERIFIED = 'unverified';
    const VERIFICATION_STATUS_VERIFIED = 'verified';
    const VERIFICATION_STATUS_REJECTED = 'rejected';

    protected $table = 'driver';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'license_number',
        'franchise_number',
        'organization_id',
        'verification_status',
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
}
