<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DiscountImage extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'discount_img';

    protected $fillable = [
        'image_front',
        'image_back',
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
