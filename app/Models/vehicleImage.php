<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class vehicleImage extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'vehicle_image';

    protected $fillable = [
        'image_front',
        'image_back',
        'image_left',
        'image_right',
    ];

     public function vehicleType()
    {
        return $this->hasOne(VehicleType::class, 'image_id');
    }
}
