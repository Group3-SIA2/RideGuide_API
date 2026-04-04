<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationFareRate extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'org_fare_rate';

    protected $fillable = [
        'organization_id',
        'fare_rate_id',
    'origin_terminal_id',
    'destination_terminal_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function fareRate(): BelongsTo
    {
        return $this->belongsTo(FareRate::class, 'fare_rate_id');
    }

    public function originTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'origin_terminal_id');
    }

    public function destinationTerminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'destination_terminal_id');
    }
}
