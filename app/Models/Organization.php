<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'organization_type',
        'organization_type_id',
        'hq_address',
        'status',
        'owner_user_id',
    ];

    protected $appends = [
        'organization_type',
        'description',
    ];

    public function getAddressAttribute(): ?string
    {
        return $this->hq_address;
    }

    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['hq_address'] = $value;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function organizationType(): BelongsTo
    {
        return $this->belongsTo(OrganizationType::class, 'organization_type_id');
    }

    public function getOrganizationTypeAttribute(): ?string
    {
        if ($this->relationLoaded('organizationType')) {
            return $this->organizationType?->name;
        }

        if (!empty($this->attributes['organization_type_id'])) {
            return $this->organizationType()->value('name');
        }

        return null;
    }

    public function getDescriptionAttribute(): ?string
    {
        if ($this->relationLoaded('organizationType')) {
            return $this->organizationType?->description;
        }

        if (!empty($this->attributes['organization_type_id'])) {
            return $this->organizationType()->value('description');
        }

        return null;
    }

    public function setOrganizationTypeAttribute(?string $value): void
    {
        $typeName = trim((string) $value);

        if ($typeName === '') {
            $this->attributes['organization_type_id'] = null;
            return;
        }

        $type = OrganizationType::withTrashed()->firstOrNew(['name' => $typeName]);

        if (!$type->exists) {
            $type->save();
        } elseif ($type->trashed()) {
            $type->restore();
        }

        $this->attributes['organization_type_id'] = $type->id;
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'organization_id');
    }

    public function organizationTerminals(): HasMany
    {
        return $this->hasMany(OrganizationTerminal::class, 'organization_id');
    }

    public function terminals(): BelongsToMany
    {
        return $this->belongsToMany(Terminal::class, 'organization_terminals', 'organization_id', 'terminal_id')
            ->withTimestamps()
            ->withPivot('id');
    }

    public function organizationUserRoles(): HasMany
    {
        return $this->hasMany(OrganizationUserRole::class, 'organization_id');
    }

    public function managerUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user_role', 'organization_id', 'user_id')
            ->withTimestamps()
            ->withPivot(['id', 'role_id', 'status', 'invited_by_user_id', 'deleted_at'])
            ->wherePivotNull('deleted_at')
            ->wherePivot('status', 'active');
    }

    public function hqAddress(): BelongsTo
    {
        return $this->belongsTo(HqAddress::class, 'hq_address');
    }
}
