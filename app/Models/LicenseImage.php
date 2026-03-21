<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseImage extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'license_image';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'image_front',
        'image_back',
    ];

     public function licenseId()
     {
         return $this->hasOne(LicenseId::class, 'image_id');
     }
}
