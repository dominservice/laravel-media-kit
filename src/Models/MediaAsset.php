<?php

namespace Dominservice\MediaKit\Models;

use Dominservice\LaravelCms\Traits\HasUuidPrimary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $model_type
 * @property string $model_id
 * @property string $collection
 * @property string $disk
 * @property string $original_path
 * @property string $original_mime
 * @property string $original_ext
 * @property int $original_size
 * @property int|null $width
 * @property int|null $height
 * @property string $hash
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * 
 * @property-read \Illuminate\Database\Eloquent\Model $model
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MediaVariant> $variants
 * @property-read int|null $variants_count
 */
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
