<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\HomepageBanner;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\WebsiteContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
    }

    private function settings(array $changes = []): array
    {
        return [...['website_name' => 'QA Store', 'contact_email' => 'hello@example.com', 'contact_phone' => '+60 (12) 345-6789',
            'address' => 'QA business address', 'footer_information' => 'Actual business information.',
            'social_links' => [['label' => 'Community', 'url' => 'https://example.com/community']],
            'remove_logo' => false, 'remove_favicon' => false, 'version' => 1], ...$changes];
    }

    private function banner(array $changes = []): array
    {
        return [...['title' => 'Seasonal collection', 'subtitle' => 'Explore the new arrivals.',
            'image_alt' => 'Products on a shelf', 'link_label' => 'Browse products', 'link_path' => '/products',
            'status' => 'draft', 'position' => 0, 'starts_at' => null, 'ends_at' => null], ...$changes];
    }

    private function createBanner(array $changes = []): HomepageBanner
    {
        $this->actingAs($this->admin)->post('/admin/banners', $this->banner($changes))->assertSessionHasNoErrors();

        return HomepageBanner::latest('id')->firstOrFail();
    }

    public function test_general_settings_have_safe_defaults_before_any_admin_visit(): void
    {
        $this->get('/')->assertSee('data-inertia="website-icon"', false)->assertInertia(fn (Assert $view) => $view
            ->where('website.website_name', 'Yazid Collective')->where('website.logo_url', null)
            ->where('website.favicon_url', null)->has('website.social_links', 0)->has('banners', 0));
        $this->assertDatabaseCount('website_settings', 0);
        $this->actingAs($this->admin)->get('/admin/website-settings')->assertInertia(fn (Assert $view) => $view
            ->where('settings.version', 1)->where('settings.website_name', 'Yazid Collective'));
    }

    public function test_settings_singleton_keeps_its_fixed_key_after_reinitialization(): void
    {
        WebsiteSetting::current()->delete();
        $this->assertSame(1, WebsiteSetting::current()->id);
        $this->assertDatabaseCount('website_settings', 1);
    }

    public function test_only_verified_admins_can_manage_website_settings_and_banners(): void
    {
        $this->get('/admin/website-settings')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->put('/admin/website-settings', $this->settings())->assertForbidden();
        $this->post('/admin/banners', $this->banner())->assertForbidden();
        $this->get('/admin/banners/create')->assertForbidden();
        $banner = $this->createBanner();
        $this->actingAs(User::factory()->create())->put("/admin/banners/$banner->id", $this->banner(['version' => 1]))->assertForbidden();
        $this->get("/admin/banners/$banner->id/edit")->assertForbidden();
        $this->admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->admin)->get('/admin/website-settings')->assertRedirect('/email/verify');
    }

    public function test_settings_publish_only_public_fields_and_do_not_override_payment_or_invoice_configuration(): void
    {
        $this->actingAs($this->admin)->put('/admin/website-settings', $this->settings([
            'currency' => 'USD', 'toyyibpay_secret' => 'never-save-this', 'seller_name' => 'Not an invoice issuer',
        ]))->assertSessionHasNoErrors();
        $this->get('/')->assertInertia(fn (Assert $view) => $view
            ->where('website.website_name', 'QA Store')->where('website.contact_email', 'hello@example.com')
            ->where('website.social_links.0.url', 'https://example.com/community')
            ->missing('website.version')->missing('website.logo_path')->missing('website.toyyibpay_secret'));
        $this->assertDatabaseCount('invoice_settings', 0);
        $this->assertSame('MYR', config('commerce.currency', 'MYR'));
        $this->assertDatabaseHas('website_changes', ['entity_type' => 'settings', 'entity_id' => 1, 'version' => 2, 'admin_id' => $this->admin->id]);
    }

    public function test_logo_and_favicon_are_reencoded_and_can_be_removed_without_destroying_audit_assets(): void
    {
        $this->actingAs($this->admin)->post('/admin/website-settings', $this->settings([
            '_method' => 'put', 'logo' => UploadedFile::fake()->image('logo.png', 200, 100),
            'favicon' => UploadedFile::fake()->image('icon.png', 64, 64),
        ]))->assertSessionHasNoErrors();
        $settings = WebsiteSetting::current();
        Storage::disk('public')->assertExists([$settings->logo_path, $settings->favicon_path]);
        $this->assertStringEndsWith('.webp', $settings->logo_path);
        $this->assertSame('image/webp', Storage::disk('public')->mimeType($settings->favicon_path));
        $this->get('/')->assertInertia(fn (Assert $view) => $view->where('website.logo_url', Storage::disk('public')->url($settings->logo_path)));
        $this->put('/admin/website-settings', $this->settings(['version' => 2, 'remove_logo' => true, 'remove_favicon' => true]))->assertSessionHasNoErrors();
        $this->assertNull($settings->fresh()->logo_path);
        $this->assertNull($settings->fresh()->favicon_path);
        Storage::disk('public')->assertExists([$settings->logo_path, $settings->favicon_path]);
        $this->assertDatabaseCount('website_changes', 2);
    }

    public function test_unsafe_social_links_oversized_fields_and_svg_uploads_are_rejected(): void
    {
        $this->actingAs($this->admin);
        foreach (['javascript:alert(1)', '//evil.test', 'http://example.com', 'https://user:pass@example.com', 'https://example.com/\\evil', 'https://example.com/a b'] as $url) {
            $this->put('/admin/website-settings', $this->settings(['social_links' => [['label' => 'Invalid', 'url' => $url]]]))
                ->assertSessionHasErrors('social_links.0.url');
        }
        $this->post('/admin/website-settings', $this->settings(['_method' => 'put',
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'favicon' => UploadedFile::fake()->image('icon.png', 64, 32), 'website_name' => str_repeat('a', 81),
            'contact_phone' => 'javascript:evil',
        ]))->assertSessionHasErrors(['logo', 'favicon', 'website_name', 'contact_phone']);
        $this->assertDatabaseCount('website_changes', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_stale_settings_updates_are_rejected_and_identical_saves_are_idempotent(): void
    {
        $this->actingAs($this->admin)->put('/admin/website-settings', $this->settings())->assertSessionHasNoErrors();
        $this->put('/admin/website-settings', $this->settings(['version' => 2]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('website_changes', 1);
        $this->put('/admin/website-settings', $this->settings(['website_name' => 'Stale name']))->assertSessionHasErrors('version');
        $this->assertSame('QA Store', WebsiteSetting::current()->website_name);
        $this->assertSame(2, WebsiteSetting::current()->version);
    }

    public function test_multipart_settings_can_save_without_any_social_links(): void
    {
        $data = $this->settings(['_method' => 'put']);
        unset($data['social_links']);
        $this->actingAs($this->admin)->post('/admin/website-settings', $data)->assertSessionHasNoErrors();
        $this->assertSame([], WebsiteSetting::current()->social_links);
    }

    public function test_failed_database_write_removes_only_newly_uploaded_assets(): void
    {
        WebsiteSetting::current();
        Storage::disk('public')->put('website/existing.webp', 'existing asset');
        Event::listen('eloquent.saving: '.WebsiteSetting::class, function (WebsiteSetting $settings): void {
            if ($settings->website_name === 'Force rollback') {
                throw new \RuntimeException('Injected storage rollback test.');
            }
        });
        try {
            app(WebsiteContentService::class)->settings($this->admin, $this->settings([
                'website_name' => 'Force rollback', 'logo' => UploadedFile::fake()->image('new.png'),
            ]));
            $this->fail('Expected the injected database failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Injected storage rollback test.', $error->getMessage());
        }
        $this->assertSame(['website/existing.webp'], Storage::disk('public')->allFiles());
        $this->assertSame(1, WebsiteSetting::current()->version);
        $this->assertDatabaseCount('website_changes', 0);
    }

    public function test_banner_publication_requires_an_image_and_alt_text_and_drafts_do_not_leak(): void
    {
        $banner = $this->createBanner();
        $this->get('/')->assertInertia(fn (Assert $view) => $view->has('banners', 0));
        $this->put("/admin/banners/$banner->id", $this->banner(['version' => 1, 'status' => 'published']))->assertSessionHasErrors('image');
        $this->put("/admin/banners/$banner->id", $this->banner(['version' => 1, 'status' => 'published', 'image_alt' => null]))->assertSessionHasErrors('image_alt');
        $this->assertSame('draft', $banner->fresh()->status);
    }

    public function test_published_banner_displays_safe_public_fields_and_can_be_unpublished(): void
    {
        $banner = $this->createBanner(['status' => 'published', 'image' => UploadedFile::fake()->image('banner.jpg', 1000, 800)]);
        $this->get('/')->assertInertia(fn (Assert $view) => $view
            ->has('banners', 1)->where('banners.0.title', 'Seasonal collection')
            ->where('banners.0.image_url', Storage::disk('public')->url($banner->image_path))
            ->missing('banners.0.image_path')->missing('banners.0.version')->missing('banners.0.status'));
        $this->put("/admin/banners/$banner->id", $this->banner(['version' => 1]))->assertSessionHasNoErrors();
        $this->get('/')->assertInertia(fn (Assert $view) => $view->has('banners', 0));
        $this->assertDatabaseCount('website_changes', 2);
        $this->assertSame('published', json_decode(DB::table('website_changes')->where('entity_type', 'banner')->where('version', 1)->value('snapshot'), true)['status']);
    }

    public function test_banner_schedules_use_inclusive_start_exclusive_end_and_deterministic_order_with_display_limit(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
        $base = $this->createBanner(['status' => 'published', 'image' => UploadedFile::fake()->image('banner.jpg')]);
        foreach (range(1, 7) as $position) {
            HomepageBanner::create([...$base->only(['image_path', 'image_alt', 'link_label', 'link_path']),
                'title' => 'Banner '.$position, 'status' => 'published', 'position' => $position, 'starts_at' => now(), 'ends_at' => now()->addHour()]);
        }
        HomepageBanner::create(['title' => 'Future', 'image_path' => $base->image_path, 'status' => 'published', 'position' => 0, 'starts_at' => now()->addMinute()]);
        HomepageBanner::create(['title' => 'Expired', 'image_path' => $base->image_path, 'status' => 'published', 'position' => 0, 'ends_at' => now()]);
        $this->get('/')->assertInertia(fn (Assert $view) => $view->has('banners', 6)
            ->where('banners.0.title', 'Seasonal collection')->where('banners.1.title', 'Banner 1')->where('banners.5.title', 'Banner 5'));
        $this->travel(1)->hours();
        $this->get('/')->assertInertia(fn (Assert $view) => $view->has('banners', 2));
    }

    public function test_banner_links_and_schedule_inputs_are_validated(): void
    {
        $this->actingAs($this->admin);
        foreach (['https://evil.test', '//evil.test', '/\\evil.test', '/%2f%2fevil.test', 'javascript:alert(1)', "/products\nbad"] as $path) {
            $this->post('/admin/banners', $this->banner(['link_path' => $path]))->assertSessionHasErrors('link_path');
        }
        $this->post('/admin/banners', $this->banner(['link_label' => null]))->assertSessionHasErrors('link_label');
        $this->post('/admin/banners', $this->banner(['starts_at' => '2026-11-20T12:00', 'ends_at' => '2026-11-20T12:00']))->assertSessionHasErrors('ends_at');
        $this->post('/admin/banners', $this->banner(['position' => -1, 'status' => 'active']))->assertSessionHasErrors(['position', 'status']);
        $this->assertDatabaseCount('homepage_banners', 0);
        $this->post('/admin/banners', $this->banner(['link_path' => '/products?sort=newest&category=1']))->assertSessionHasNoErrors();
    }

    public function test_banner_replacement_preserves_old_assets_and_stale_edits_cannot_overwrite(): void
    {
        $banner = $this->createBanner(['image' => UploadedFile::fake()->image('old.png')]);
        $oldPath = $banner->image_path;
        $url = "/admin/banners/$banner->id";
        $this->put($url, $this->banner(['version' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('website_changes', 1);
        $this->post($url, $this->banner(['_method' => 'put', 'version' => 1, 'image' => UploadedFile::fake()->image('new.png')]))->assertSessionHasNoErrors();
        $this->assertNotSame($oldPath, $banner->fresh()->image_path);
        Storage::disk('public')->assertExists([$oldPath, $banner->fresh()->image_path]);
        $this->put($url, $this->banner(['version' => 1, 'title' => 'Stale']))->assertSessionHasErrors('version');
        $this->put($url, $this->banner())->assertSessionHasErrors('version');
        $this->assertSame(2, $banner->fresh()->version);
        $this->assertDatabaseCount('website_changes', 2);
    }
}
