<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HqAddress extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'hq_address';

    protected $fillable = [
        'barangay',
        'street',
        'subdivision',
        'floor_unit_room',
        'lat',
        'lng',
    ];

    /**
     * The organization that owns this address.
     */
    public function organization()
    {
        return $this->hasOne(Organization::class, 'hq_address');
    }

    /**
     * Returns a single-line formatted address string.
     */
    public function getFormattedAttribute(): string
    {
        return implode(', ', array_filter([
            $this->floor_unit_room,
            $this->subdivision,
            $this->street,
            $this->barangay,
        ]));
    }
}