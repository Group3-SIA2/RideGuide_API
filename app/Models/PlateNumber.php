<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PlateNumber extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'plate_number';

    protected $fillable = [
        'plate_number',
    ];

     public function vehicle()
    {
        return $this->hasOne(vehicle::class, 'plate_number_id');
     }
}
