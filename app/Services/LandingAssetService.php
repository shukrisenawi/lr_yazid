<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LandingAssetService
{
    public function response(string $path): BinaryFileResponse
    {
        // Never expose application files, PHP uploads, build manifests or dotfiles.
        abort_unless(preg_match('#^(?:build/assets/|images/|storage/)[a-zA-Z0-9_./-]+\.(?:css|js|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf)$#D', $path)
            || in_array($path, ['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'robots.txt'], true), 404);
        abort_if(str_contains($path, '..') || str_contains($path, '/.'), 404);
        $root = str_starts_with($path, 'storage/') ? realpath(storage_path('app/public')) : realpath(public_path());
        $file = realpath(public_path($path));
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        $response = new BinaryFileResponse($file);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = match ($extension) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'svg' => 'image/svg+xml',
            default => null,
        };
        if ($type) {
            $response->headers->set('Content-Type', $type);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
