<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FareRate extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'fare_rate';

    protected $fillable = [
        'base_fare_4KM',
        'per_km_rate',
        'route_standard_fare',
        'effective_date',
    ];

    protected $casts = [
        'base_fare_4KM' => 'float',
        'per_km_rate' => 'float',
        'route_standard_fare' => 'float',
        'effective_date' => 'date',
    ];
}
