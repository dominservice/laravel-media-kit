<?php

namespace Dominservice\MediaKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    protected $table = 'media_assets';

    /** UUID jako klucz główny */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'model_type', 'model_id', 'collection',
        'disk', 'original_path', 'original_mime', 'original_ext', 'original_size',
        'width', 'height',
        'hash', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $asset) {
            if (!$asset->id) {
                $asset->id = (string) Str::uuid();
            }
        });
    }

    /** Polimorficzny właściciel (np. Post, Product) */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** Dostępne warianty tego assetu (webp/avif/jpeg itp.) */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'asset_id');
    }
}
