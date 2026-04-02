<?php

namespace Dominservice\MediaKit\Models;

use Dominservice\MediaKit\Traits\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaLibrary extends Model
{
    use HasMedia;

    protected $table = 'media_libraries';

    protected $fillable = [
        'id',
        'key',
        'name',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $library): void {
            if (! is_string($library->id) || $library->id === '') {
                $library->id = (string) Str::uuid();
            }
        });
    }

    public static function defaultLibrary(): self
    {
        $key = (string) config('media-kit.admin.library.default_key', 'global');
        $name = (string) config('media-kit.admin.library.default_name', 'Main media library');

        return static::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $name]
        );
    }
}
