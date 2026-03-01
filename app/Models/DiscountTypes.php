<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountTypes extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'commuter_classification_types';

    protected $primaryKey = 'id';

    protected $fillable = [
        'classification_name',
    ];
}
