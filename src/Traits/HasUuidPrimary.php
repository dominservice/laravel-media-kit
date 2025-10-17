<?php

namespace Dominservice\LaravelCms\Traits;

trait HasUuidPrimary
{
    use HasUuid;

    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * @return bool
     */
    public function getKeyName()
    {
        return 'uuid';
    }

    /**
     * Get the auto-incrementing key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'uuid';
    }
}
