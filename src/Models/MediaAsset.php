<?php

namespace Dominservice\MediaKit\Models;

use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use HasUuidPrimary;
    
    protected $table = 'media_assets';


    protected $fillable = [
        'model_type', 'model_id', 'collection',
        'disk', 'original_path', 'original_mime', 'original_ext', 'original_size',
        'width', 'height',
        'hash', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /** Polimorficzny właściciel (np. Post, Product) */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** Dostępne warianty tego assetu (webp/avif/jpeg itp.) */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'asset_uuid');
    }
}
