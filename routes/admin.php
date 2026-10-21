<?php

use Dominservice\MediaKit\Http\Controllers\Admin\MediaLibraryController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('media-kit.admin.prefix', 'admin/media'), '/');
$routeNamePrefix = rtrim((string) config('media-kit.admin.route_name_prefix', 'admin.media.'), '.').'.';
$middleware = array_values(array_filter((array) config('media-kit.admin.middleware', ['web', 'auth'])));
$permissions = (array) config('media-kit.admin.permissions', []);

$withPermission = static function (?string $permission): array {
    return $permission ? ['permission:'.$permission] : [];
};

Route::middleware($middleware)
    ->prefix($prefix)
    ->as($routeNamePrefix)
    ->group(function () use ($permissions, $withPermission): void {
        Route::get('/', [MediaLibraryController::class, 'index'])
            ->middleware($withPermission($permissions['view'] ?? null))
            ->name('index');

        Route::get('/browse', [MediaLibraryController::class, 'browse'])
            ->middleware($withPermission($permissions['view'] ?? null))
            ->name('browse');

        Route::get('/thumbnail/{asset}', [MediaLibraryController::class, 'thumbnail'])
            ->middleware($withPermission($permissions['view'] ?? null))
            ->whereUuid('asset')
            ->name('thumbnail');

        Route::post('/', [MediaLibraryController::class, 'store'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->name('store');

        Route::post('/libraries', [MediaLibraryController::class, 'storeLibrary'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->name('libraries.store');

        Route::post('/folders', [MediaLibraryController::class, 'storeFolder'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->name('folders.store');

        Route::put('/folders/{folder}', [MediaLibraryController::class, 'updateFolder'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->name('folders.update');

        Route::delete('/folders/{folder}', [MediaLibraryController::class, 'destroyFolder'])
            ->middleware($withPermission($permissions['delete'] ?? null))
            ->name('folders.destroy');

        Route::post('/bulk/move', [MediaLibraryController::class, 'bulkMove'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->name('bulk.move');

        Route::post('/bulk/delete', [MediaLibraryController::class, 'bulkDestroy'])
            ->middleware($withPermission($permissions['delete'] ?? null))
            ->name('bulk.destroy');

        Route::put('/{asset}', [MediaLibraryController::class, 'update'])
            ->middleware($withPermission($permissions['upload'] ?? null))
            ->whereUuid('asset')
            ->name('update');

        Route::delete('/{asset}', [MediaLibraryController::class, 'destroy'])
            ->middleware($withPermission($permissions['delete'] ?? null))
            ->whereUuid('asset')
            ->name('destroy');
    });
