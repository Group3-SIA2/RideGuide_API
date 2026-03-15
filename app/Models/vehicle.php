<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class vehicle extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'vehicle';

    protected $fillable = [
        'driver_id',
        'vehicle_type_id',
        'plate_number_id',
        'status',
        'verification_status',
        'rejection_reason',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function plateNumber()
    {
        return $this->belongsTo(PlateNumber::class, 'plate_number_id');
    }
}
