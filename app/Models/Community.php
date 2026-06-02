<?php

namespace App\Models;

use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['parent_id', 'name', 'slug', 'description'])]
class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Community::class, 'parent_id');
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }
}
