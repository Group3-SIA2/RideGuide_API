<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRole extends Pivot
{
    use HasUuids;

    protected $table = 'user_role';

    protected $fillable = [
        'user_id',
        'role_id',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
}
