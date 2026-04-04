<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use HasUuids, SoftDeletes;
    
    protected $table = 'feedback';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'commuter_id',
        'trip_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function commuter()
    {
        return $this->belongsTo(Commuter::class, 'commuter_id');
    }

    public function images()
    {
        return $this->hasMany(FeedbackImage::class, 'feedback_id');
    }

    public function videos()
    {
        return $this->hasMany(FeedbackVideo::class, 'feedback_id');
    }
}
