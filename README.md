# Dominservice Laravel Media Kit

[![Latest Version on Packagist](https://img.shields.io/packagist/v/dominservice/laravel-media-kit.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-media-kit)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/laravel-media-kit.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-media-kit)
[![License](https://img.shields.io/packagist/l/dominservice/laravel-media-kit.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-media-kit)

`dominservice/laravel-media-kit` is a reusable media layer for Laravel applications that need image and video processing, responsive variants and a shared admin media library.

It is designed to work well in two modes at once:
- as a clean standalone media toolkit,
- as the media backbone for `dominservice/laravel-cms` and larger modular systems.

## Key Features

- Laravel 9–13 support,
- responsive image variants in AVIF, WebP, JPEG and PNG,
- lazy or eager variant generation,
- media kinds for domain-specific collections,
- shared media library for admin panels,
- attach existing assets without re-uploading the same file,
- image and video support,
- configurable admin routes, middleware, permissions and layout integration,
- publishable package views for host-project theming,
- CDN and signed URL support.

## Installation

```bash
composer require dominservice/laravel-media-kit

php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-config
php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-migrations
php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-views

php artisan migrate
```

## Core Usage

### Add media to a model

```php
use Dominservice\MediaKit\Traits\HasMedia;
use Dominservice\MediaKit\Traits\HasMediaKinds;

class Post extends Model
{
    use HasMedia;
    use HasMediaKinds;
}
```

```php
$post->addMedia($request->file('cover'), 'featured');
```

### Reuse an existing asset

```php
$post->attachExistingMedia($mediaAsset, 'featured');
$post->attachExistingMedia($uuid, 'gallery', 'keep');
```

This is useful for shared media libraries, CMS block builders and projects where the same asset may be used in many places.

## Shared Admin Media Library

The package now includes an optional admin media library module.

It provides:
- reusable routes and controllers inside the package,
- upload, list and delete actions,
- a dedicated `media_libraries` table,
- publishable views,
- flexible route, permission and layout integration.

### Admin configuration

```php
'admin' => [
    'enabled' => true,
    'prefix' => 'admin/media',
    'route_name_prefix' => 'admin.media.',
    'middleware' => ['web', 'auth'],
    'permissions' => [
        'view' => 'media.view',
        'upload' => 'media.upload',
        'delete' => 'media.delete',
    ],
    'layout' => [
        'mode' => 'extends',
        'view' => 'layouts.admin',
        'section' => 'content',
        'component' => 'admin-layout',
    ],
    'library' => [
        'view' => 'mediakit::admin.library.index',
        'default_key' => 'global',
        'default_name' => 'Main media library',
        'default_collection' => 'library',
        'per_page' => 18,
    ],
],
```

This keeps the route and controller logic inside the package while allowing the project to override only the Blade views.

## CMS Integration

`dominservice/laravel-cms` can use Media Kit as its media backend.

Recommended setup:
- keep media library routes and controller inside `laravel-media-kit`,
- keep CMS content logic inside `laravel-cms`,
- publish views from both packages,
- override the views in the project to match the design system.

This gives you a reusable package architecture and avoids duplicating media logic per project.

## Kinds and Variants

Kinds let you define domain-aware media contracts such as `avatar`, `gallery`, `video_avatar` or `video_poster`.

```php
'kinds' => [
    'avatar' => [
        'collection' => 'avatar',
        'display' => 'lg',
        'variants' => ['thumb', 'sm', 'md', 'lg'],
    ],
    'video_avatar' => [
        'collection' => 'video',
        'renditions' => ['hd', 'sd', 'mobile'],
        'poster_kind' => 'video_poster',
    ],
],
```

## Views and Theming

The package is prepared for reuse across projects.

```bash
php artisan vendor:publish --provider="Dominservice\MediaKit\MediaKitServiceProvider" --tag=mediakit-views
```

Recommended approach:
- keep routes, controller actions, models and services in the package,
- publish the views,
- override only the UI layer in the host project.

This is especially useful when pairing Media Kit with a custom CMS or admin template such as Metronic.

## Extensibility

The package is intentionally configurable instead of hardcoded.

You can override:
- models,
- route prefix and route names,
- admin middleware and permissions,
- layout mode and view integration,
- accepted file extensions,
- kinds and variants,
- published views.

This allows each project to adapt the package without forking the package logic.

## License

MIT
