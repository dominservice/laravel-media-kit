<?php

namespace Dominservice\MediaKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $asset_uuid
 * @property string $name
 * @property string $format
 * @property string $disk
 * @property string $path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $quality
 * @property int|null $size
 * @property \Illuminate\Support\Carbon|null $generated_at
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * 
 * @property-read MediaAsset $asset
 */
class MediaVariant extends Model
{
    protected $table = 'media_variants';

    protected $fillable = [
        'asset_uuid',
        'name', 'format',
        'disk', 'path',
        'width', 'height', 'quality', 'size',
        'generated_at', 'meta',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'meta' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'asset_uuid');
    }
}
