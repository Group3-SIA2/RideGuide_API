<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UsersEmergencyContact extends Model
{
    use HasUuids;
    
    protected $table = 'users_emergency_contact';

    protected $fillable = [
        'user_id',
        'emergency_contact_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function emergencyContact()
    {
        return $this->belongsTo(EmergencyContact::class, 'emergency_contact_id');
    }
}
