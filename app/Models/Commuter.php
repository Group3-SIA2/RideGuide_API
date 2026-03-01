<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Discount;

class Commuter extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'commuter';

    protected $fillable = [
        'user_id',
        'discount_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
}
