<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use app\Models\User;

class UserProfile extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'users_profile';

    protected $fillable = [
        'user_id',
        'birthdate',
        'gender',
        'profile_image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
