<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Barangay extends Model
{
    use HasUuids;
    use HasFactory;

    protected $table = 'barangays';

    protected $fillable = [
        'name',
        'code',
        'center_latitude',
        'center_longitude',
        'north_latitude',
        'south_latitude',
        'east_longitude',
        'west_longitude',
    ];

    protected $casts = [
        'center_latitude' => 'float',
        'center_longitude' => 'float',
        'north_latitude' => 'float',
        'south_latitude' => 'float',
        'east_longitude' => 'float',
        'west_longitude' => 'float',
    ];


}
