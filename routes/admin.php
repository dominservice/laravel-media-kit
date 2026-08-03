<?php

use Dominservice\MediaKit\Http\Controllers\Admin\MediaLibraryController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('media-kit.admin.prefix', 'admin/media'), '/');
$routeNamePrefix = rtrim((string) config('media-kit.admin.route_name_prefix', 'admin.media.'), '.') . '.';
$middleware = array_values(array_filter((array) config('media-kit.admin.middleware', ['web', 'auth'])));
$permissions = (array) config('media-kit.admin.permissions', []);

$withPermission = static function (?string $permission) use ($middleware): array {
    return array_values(array_filter(array_merge(
        $middleware,
        $permission ? ['permission:' . $permission] : []
    )));
};

Route::middleware($middleware)
    ->prefix($prefix)
    ->as($routeNamePrefix)
    ->group(function () use ($permissions, $withPermission): void {
        Route::get('/', [MediaLibraryController::class, 'index'])
            ->middleware($withPermission($permissions['view'] ?? null))
            ->name('index');

        Route::post('/', [MediaLibraryController::class, 'store'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->name('store');

        Route::put('/{asset}', [MediaLibraryController::class, 'update'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->whereUuid('asset')
            ->name('update');

        Route::delete('/{asset}', [MediaLibraryController::class, 'destroy'])
            ->middleware($withPermission($permissions['delete'] ?? null))
            ->whereUuid('asset')
            ->name('destroy');
    });
