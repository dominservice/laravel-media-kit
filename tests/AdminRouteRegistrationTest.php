<?php

namespace Dominservice\MediaKit\Tests;

use Illuminate\Support\Facades\Route;

final class AdminRouteRegistrationTest extends TestCase
{
    public function test_admin_routes_are_disabled_by_default_while_public_delivery_remains_available(): void
    {
        self::assertFalse(Route::has('admin.media.index'));
        self::assertTrue(Route::has('mediakit.media.show'));
    }
}
