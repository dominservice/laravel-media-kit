<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} - Media Library</title>
    <style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f8fa;margin:0;color:#181c32}
        .container{max-width:1280px;margin:0 auto;padding:32px 20px}
        .card{background:#fff;border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.08);padding:24px}
    </style>
</head>
<body>
<div class="container">
    @yield('content')
</div>
</body>
</html>
