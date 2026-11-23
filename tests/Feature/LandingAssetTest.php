<?php

namespace Tests\Feature;

use App\Services\LandingAssetService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LandingAssetTest extends TestCase
{
    public function test_shared_public_images_are_available_from_the_landing_root(): void
    {
        $response = app(LandingAssetService::class)->response('images/brand/logo-full.png');
        $this->assertSame(realpath(public_path('images/brand/logo-full.png')), $response->getFile()->getPathname());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_private_files_and_traversal_are_not_exposed(): void
    {
        foreach (['.env', 'index.php', '../.env', 'build/manifest.json', 'images/../favicon.svg', 'storage/test.php', 'images/.hidden.png', 'images/missing.png'] as $path) {
            try {
                app(LandingAssetService::class)->response($path);
                $this->fail('Unexpected access: '.$path);
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }
}
