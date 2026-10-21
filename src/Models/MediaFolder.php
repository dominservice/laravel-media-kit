<?php

namespace Dominservice\MediaKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    protected $table = 'media_folders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'media_library_id',
        'key',
        'name',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $folder): void {
            if (! is_string($folder->id) || $folder->id === '') {
                $folder->id = (string) Str::uuid();
            }
        });
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'media_library_id');
    }
}
