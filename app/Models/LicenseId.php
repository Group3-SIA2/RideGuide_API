<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseId extends Model
{
    const VERIFICATION_STATUS_UNVERIFIED = 'unverified';
    const VERIFICATION_STATUS_VERIFIED = 'verified';
    const VERIFICATION_STATUS_REJECTED = 'rejected';

    use HasUuids, SoftDeletes;

    protected $table = 'license_id';

    protected $primaryKey = 'id';

    protected $keyType = 'string';
    protected $fillable = [
        'license_id',
        'image_id',
        'verification_status',
        'rejection_reason',
    ];

    public function image()
    {
        return $this->belongsTo(LicenseImage::class, 'image_id');
    }
}
