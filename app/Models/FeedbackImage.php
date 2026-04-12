<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackImage extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'feedback_image';

    protected $fillable = [
        'feedback_id',
        'image',
    ];

    public function feedback()
    {
        return $this->belongsTo(Feedback::class, 'feedback_id');
    }
}
