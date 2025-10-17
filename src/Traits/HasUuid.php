<?php

namespace Dominservice\LaravelCms\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasUuid
{
    use HasUuids;

    /**
     * @param $query
     * @param $uuid
     * @param $first
     * @return mixed
     */
    public function scopeUuid($query, $uuid)
    {
        return $query->where('uuid', $uuid)->first();
    }

    /**
     * Scope a query to only include models matching the supplied ID or UUID.
     * Returns the model by default, or supply a second flag `false` to get the Query Builder instance.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     *
     * @param  \Illuminate\Database\Schema\Builder $query The Query Builder instance.
     * @param  string                              $uuid  The UUID of the model.
     * @param  bool|true                           $first Returns the model by default, or set to `false` to chain for query builder.
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder
     */
    public function scopeIdOrUuId($query, $id_or_uuid)
    {
        return $query->where(function ($query) use ($id_or_uuid) {
            $query->where('id', $id_or_uuid)
                ->orWhere('uuid', $id_or_uuid);
        })->first();
    }
}
