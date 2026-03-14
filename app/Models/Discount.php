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
        'ID_number',
        'ID_image_id',
        'classification_type_id',
    ];

    public function classificationType()
    {
        return $this->belongsTo(DiscountTypes::class, 'classification_type_id');
    }

    public function idImage()
    {
        return $this->belongsTo(DiscountImage::class, 'ID_image_id');
    }

    public function commuter()
    {
        return $this->hasOne(Commuter::class, 'discount_id');
    }
}
