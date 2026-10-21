<?php

namespace Dominservice\MediaKit\Tests;

use Illuminate\Support\Facades\Route;

final class EnabledAdminRouteRegistrationTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-kit.admin.enabled', true);
    }

    public function test_admin_routes_are_registered_when_explicitly_enabled(): void
    {
        self::assertTrue(Route::has('admin.media.index'));
        self::assertTrue(Route::has('admin.media.store'));
        self::assertTrue(Route::has('admin.media.browse'));
        self::assertTrue(Route::has('admin.media.thumbnail'));
        self::assertTrue(Route::has('admin.media.libraries.store'));
        self::assertTrue(Route::has('admin.media.folders.store'));
        self::assertTrue(Route::has('admin.media.bulk.move'));
        self::assertTrue(Route::has('admin.media.bulk.destroy'));
        self::assertTrue(Route::has('mediakit.media.show'));
    }
}
