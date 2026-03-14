<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EmergencyContact extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'emergency_contact';

    protected $fillable = [
        'contact_name',
        'contact_phone_number',
        'contact_relationship',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
