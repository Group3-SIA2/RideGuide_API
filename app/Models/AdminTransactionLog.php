<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTransactionLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'actor_user_id',
        'actor_email',
        'module',
        'transaction_type',
        'reference_type',
        'reference_id',
        'status',
        'reason',
        'before_data',
        'after_data',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}