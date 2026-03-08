<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory, HasUuids;

    const SUPER_ADMIN  = 'super_admin';
    const ADMIN        = 'admin';
    const DRIVER       = 'driver';
    const COMMUTER     = 'commuter';
    const ORGANIZATION = 'organization';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the users that belong to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
            ->using(UserRole::class)
            ->withTimestamps();
    }
}
