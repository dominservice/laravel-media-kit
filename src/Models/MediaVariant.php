<?php

namespace Dominservice\MediaKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaVariant extends Model
{
    protected $table = 'media_variants';

    protected $fillable = [
        'asset_id',
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
        return $this->belongsTo(MediaAsset::class, 'asset_id');
    }
}
