<?php

use App\Http\Controllers\ContentPageController;
use App\Models\ContentPage;
use App\Services\LandingAssetService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$request = Request::capture();
if ($request->path() !== '/') {
    $app->make(Kernel::class)->bootstrap();
    try {
        app(LandingAssetService::class)->response($request->path())->prepare($request)->send();
    } catch (HttpException $exception) {
        http_response_code($exception->getStatusCode());
    }
    exit;
}

$app->booted(function () use ($app) {
    // This document root deliberately exposes only the landing page and its assets.
    $app['router']->get('/', function (Request $request) {
        $page = ContentPage::where('custom_domain', $request->getHost())
            ->where('is_landing', true)->where('status', 'published')->firstOrFail();

        return app(ContentPageController::class)->render($page);
    })->middleware('web');
});

$app->handleRequest($request);
