<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use app\Models\User;

class Commuter extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'commuter';

    protected $fillable = [
        'user_id',
        'commuter_classification',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
