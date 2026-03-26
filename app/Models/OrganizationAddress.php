<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrganizationAddress extends Model
{
    
    use SoftDeletes, HasUuids;
    protected $table = 'hq_address';
    protected $fillable = [
        'barangay',
        'street',
        'subdivision',
        'Floor/Unit/Room #',
        'lat',
        'lng'
    ];
    
     public function organization()
     {
         return $this->hasOne(Organization::class, 'hq_address');
     }
}