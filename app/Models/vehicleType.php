<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class vehicleType extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'vehicle_types';

    protected $fillable = [
        'vehicle_type',
        'description',
        'image_id',
    ];

     public function vehicleImage()
    {
        return $this->belongsTo(vehicleImage::class, 'image_id');
    }
}
