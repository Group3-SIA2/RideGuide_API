<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'discounts';

    protected $fillable = [
        'classification_type_id',
        'ID_number',
        'ID_image_path',
    ];

    public function classificationType()
    {
        return $this->belongsTo(DiscountTypes::class, 'classification_type_id');
    }
}
