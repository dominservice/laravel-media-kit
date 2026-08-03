<?php

namespace Dominservice\MediaKit\Contracts;

use Dominservice\MediaKit\Models\MediaAsset;

interface MediaUsageResolverContract
{
    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    public function resolve(MediaAsset $usageAsset, array $fallback): array;
}
