<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'approval_status', 'owner_id'];

    protected $appends = ['logo_url', 'cover_url'];

    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path ? route('shops.image', ['shop' => $this->id, 'kind' => 'logo']) : null);
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn () => $this->cover_path ? route('shops.image', ['shop' => $this->id, 'kind' => 'cover']) : null);
    }

    protected function casts(): array
    {
        return ['business_hours' => 'array', 'is_active' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved')->where('is_active', true)->whereHas('owner', fn ($owner) => $owner->where('account_status', 'active'));
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
