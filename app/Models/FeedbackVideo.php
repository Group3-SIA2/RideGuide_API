<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackVideo extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'feedback_video';

    protected $fillable = [
        'feedback_id',
        'video',
    ];

    public function feedback()
    {
        return $this->belongsTo(Feedback::class, 'feedback_id');
    }
}
