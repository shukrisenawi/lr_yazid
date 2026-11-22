<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\HttpSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_authenticated_inertia_responses_are_not_shared_or_stored(): void
    {
        foreach ([$this->get('/'), $this->actingAs(User::factory()->create())->get('/orders')] as $response) {
            $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'same-origin')->assertHeader('Pragma', 'no-cache');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
            $this->assertStringNotContainsString('public', $response->headers->get('Cache-Control'));
        }
    }

    public function test_health_json_validation_and_error_responses_receive_baseline_headers(): void
    {
        foreach ([$this->get('/up'), $this->get('/missing-security-qa-page'),
            $this->postJson('/payments/toyyibpay/callback', [])] as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->get('/missing-security-qa-page')->assertNotFound();
        $this->postJson('/payments/toyyibpay/callback', [])->assertUnprocessable();
    }

    public function test_unauthenticated_redirects_and_authorization_errors_cannot_be_cached(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login')->assertHeader('X-Frame-Options', 'DENY');
        $response = $this->actingAs(User::factory()->create())->get('/admin/orders')->assertForbidden();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_hsts_is_only_emitted_for_actual_secure_production_requests(): void
    {
        $this->app['env'] = 'production';
        $secure = HttpSecurity::apply(new Response, Request::create('https://shop.example.com/up'));
        $this->assertSame('max-age=15552000', $secure->headers->get('Strict-Transport-Security'));
        $insecure = HttpSecurity::apply(new Response, Request::create('http://shop.example.com/up'));
        $this->assertFalse($insecure->headers->has('Strict-Transport-Security'));
        $this->app['env'] = 'testing';
        $local = HttpSecurity::apply(new Response, Request::create('https://localhost/up'));
        $this->assertFalse($local->headers->has('Strict-Transport-Security'));
    }

    public function test_untrusted_forwarded_headers_do_not_enable_hsts(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://localhost']);
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'attacker.example.com'])
            ->get('http://localhost/up')->assertBadRequest()->assertHeaderMissing('Strict-Transport-Security');
        $this->get('http://untrusted.on-forge.com/up')->assertBadRequest()->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_explicit_proxy_can_forward_scheme_and_client_ip_but_not_host(): void
    {
        $this->app['env'] = 'production';
        config(['security.trusted_proxies' => '10.1.2.3/32', 'app.url' => 'https://shop.company.my']);
        Route::get('/proxy-qa', fn (Request $request) => response()->json(['scheme' => $request->getScheme(), 'host' => $request->getHost(), 'client' => $request->ip()]));
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->withHeaders([
            'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443', 'X-Forwarded-Host' => 'attacker.example.com', 'X-Forwarded-For' => '203.0.113.9',
        ])->get('http://shop.company.my/proxy-qa')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=15552000')
            ->assertJson(['scheme' => 'https', 'host' => 'shop.company.my', 'client' => '203.0.113.9']);
    }

    public function test_production_requires_the_configured_https_host_and_port(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://shop.company.my']);
        $this->get('https://shop.company.my/up')->assertOk();
        $this->get('https://attacker.company.my/up')->assertBadRequest();
        $this->get('https://shop.company.my:8443/up')->assertBadRequest();
        $this->get('http://shop.company.my/up')->assertBadRequest();
        config(['app.url' => null]);
        $this->get('https://shop.company.my/up')->assertBadRequest();
    }

    public function test_existing_stronger_csp_and_response_status_and_content_are_preserved(): void
    {
        $response = new Response('Original response', 202, ['Content-Security-Policy' => "default-src 'self'; frame-ancestors 'none'"]);
        HttpSecurity::apply($response, Request::create('http://localhost'));
        $this->assertSame("default-src 'self'; frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('Original response', $response->getContent());
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_appearance_cookie_is_limited_to_known_theme_values(): void
    {
        $this->withUnencryptedCookies(['appearance' => "dark';alert('theme-qa');//"])->get('/')->assertOk()->assertDontSee('theme-qa');
        $this->assertSame('system', View::shared('appearance'));
        $this->withUnencryptedCookies(['appearance' => 'dark'])->get('/')->assertOk();
        $this->assertSame('dark', View::shared('appearance'));
    }
}
