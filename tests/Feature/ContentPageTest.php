<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();
    }

    private function data(array $changes = []): array
    {
        return [...['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>Real business information.</p>',
            'seo_title' => 'About our business', 'meta_description' => 'Find out about our business.',
            'status' => 'draft', 'show_in_footer' => true], ...$changes];
    }

    private function create(array $changes = []): ContentPage
    {
        $this->actingAs($this->admin)->post('/admin/content-pages', $this->data($changes))->assertSessionHasNoErrors();

        return ContentPage::where('slug', $changes['slug'] ?? 'about-us')->firstOrFail();
    }

    public function test_only_verified_administrators_can_manage_content(): void
    {
        $this->get('/admin/content-pages')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/content-pages')->assertForbidden();
        $this->post('/admin/content-pages', $this->data())->assertForbidden();
        $page = $this->create();
        $this->actingAs(User::factory()->create())->get("/admin/content-pages/$page->id/edit")->assertForbidden();
        $this->put("/admin/content-pages/$page->id", $this->data(['version' => 1]))->assertForbidden();
        $this->admin->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->admin)->get('/admin/content-pages')->assertRedirect('/email/verify');
    }

    public function test_drafts_and_their_metadata_are_not_public_even_to_admins(): void
    {
        $page = $this->create();
        $this->get('/pages/about-us')->assertNotFound();
        $this->get('/')->assertInertia(fn (Assert $view) => $view->has('footerPages', 0));
        $this->get("/admin/content-pages/$page->id/edit")->assertInertia(fn (Assert $view) => $view
            ->component('admin/content/Edit')->where('contentPage.content', '<p>Real business information.</p>')
            ->has('revisions', 1)->where('revisions.0.version', 1));
        $this->assertDatabaseHas('content_page_revisions', ['content_page_id' => $page->id, 'admin_id' => $this->admin->id, 'version' => 1]);
    }

    public function test_published_page_has_seo_fields_and_footer_visibility_without_private_audit_data(): void
    {
        $this->create(['status' => 'published']);
        auth()->logout();
        $this->get('/pages/about-us')->assertInertia(fn (Assert $view) => $view->component('public/ContentPage')
            ->where('contentPage.title', 'About Us')->where('contentPage.seo_title', 'About our business')
            ->where('contentPage.meta_description', 'Find out about our business.')
            ->missing('contentPage.id')->missing('contentPage.version')->missing('revisions')
            ->has('footerPages', 1)->where('footerPages.0.slug', 'about-us')->missing('footerPages.0.content'));
    }

    public function test_unpublishing_removes_public_access_and_footer_links_without_deleting_history(): void
    {
        $page = $this->create(['status' => 'published']);
        $this->put("/admin/content-pages/$page->id", $this->data(['version' => 1]))->assertSessionHasNoErrors();
        $this->get('/pages/about-us')->assertNotFound();
        $this->get('/')->assertInertia(fn (Assert $view) => $view->has('footerPages', 0));
        $this->assertDatabaseCount('content_page_revisions', 2);
        $old = DB::table('content_page_revisions')->where('version', 1)->first();
        $this->assertSame('published', json_decode($old->snapshot, true)['status']);
    }

    public function test_rich_content_is_sanitized_and_empty_published_content_is_rejected(): void
    {
        $page = $this->create(['status' => 'published', 'content' => '<script>alert(1)</script><p onclick="alert(2)">Safe <strong>content</strong><a href="javascript:alert(3)">link</a></p>']);
        $this->assertStringNotContainsString('<script', $page->content);
        $this->assertStringNotContainsString('onclick', $page->content);
        $this->assertStringNotContainsString('javascript:', $page->content);
        $this->assertStringContainsString('<strong>content</strong>', $page->content);
        foreach (['<p></p>', '<p>&nbsp;</p>', '<script>bad</script>'] as $content) {
            $this->put("/admin/content-pages/$page->id", $this->data(['version' => 1, 'status' => 'published', 'content' => $content]))->assertSessionHasErrors('content');
        }
        $this->assertSame(1, $page->fresh()->version);
    }

    public function test_stale_edits_cannot_overwrite_newer_content_and_identical_saves_do_not_add_revisions(): void
    {
        $page = $this->create();
        $url = "/admin/content-pages/$page->id";
        $this->put($url, $this->data(['version' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('content_page_revisions', 1);
        $this->put($url, $this->data(['version' => 1, 'title' => 'New title']))->assertSessionHasNoErrors();
        $this->put($url, $this->data(['version' => 1, 'title' => 'Stale title']))->assertSessionHasErrors('version');
        $this->assertSame('New title', $page->fresh()->title);
        $this->assertSame(2, $page->fresh()->version);
        $this->assertDatabaseCount('content_page_revisions', 2);
        $this->put($url, $this->data(['title' => 'Missing version']))->assertSessionHasErrors('version');
    }

    public function test_slugs_status_and_seo_lengths_are_validated_and_duplicate_slugs_are_rejected(): void
    {
        $this->create();
        $this->post('/admin/content-pages', $this->data())->assertSessionHasErrors('slug');
        $this->post('/admin/content-pages', $this->data(['slug' => '../admin', 'status' => 'hidden', 'seo_title' => str_repeat('a', 201), 'meta_description' => str_repeat('a', 301)]))
            ->assertSessionHasErrors(['slug', 'status', 'seo_title', 'meta_description']);
        $this->assertDatabaseCount('content_pages', 1);
    }

    public function test_admin_filters_and_opt_out_footer_are_independent_of_public_visibility(): void
    {
        $this->create();
        $this->create(['title' => 'Shipping Policy', 'slug' => 'shipping-policy', 'status' => 'published', 'show_in_footer' => false]);
        $this->get('/admin/content-pages?search=Shipping&status=published')->assertInertia(fn (Assert $view) => $view
            ->has('pages.data', 1)->where('pages.data.0.title', 'Shipping Policy')->missing('pages.data.0.content')
            ->where('documentRoots.standard', public_path())->where('documentRoots.landing', base_path('landing-public')));
        $this->get('/pages/shipping-policy')->assertOk()->assertInertia(fn (Assert $view) => $view->has('footerPages', 0));
    }

    public function test_landing_page_has_guest_link_and_custom_domain_and_can_be_unpublished(): void
    {
        $page = $this->create(['is_landing' => true, 'custom_domain' => ' Promo.example.com ', 'status' => 'published']);
        $this->assertSame('promo.example.com', $page->custom_domain);
        auth()->logout();
        $this->get('/lp/about-us')->assertInertia(fn (Assert $view) => $view->component('landing/Show')->where('contentPage.title', 'About Us')->missing('contentPage.custom_domain'));
        $this->get('http://promo.example.com/')->assertInertia(fn (Assert $view) => $view->component('landing/Show'));
        $this->actingAs($this->admin)->put("/admin/content-pages/$page->id", $this->data(['version' => 1, 'is_landing' => true, 'custom_domain' => 'promo.example.com']))->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/lp/about-us')->assertNotFound();
        $this->get('http://promo.example.com/')->assertNotFound();
    }

    public function test_normal_pages_are_not_landing_pages_and_domain_values_are_validated(): void
    {
        $this->create(['status' => 'published']);
        $this->get('/lp/about-us')->assertNotFound();
        foreach (['https://promo.example.com', 'promo.example.com/path', '*.example.com', '-promo.example.com'] as $domain) {
            $this->post('/admin/content-pages', $this->data(['slug' => 'promo', 'is_landing' => true, 'custom_domain' => $domain]))->assertSessionHasErrors('custom_domain');
        }
        $this->create(['slug' => 'promo', 'is_landing' => true, 'custom_domain' => 'promo.example.com']);
        $this->post('/admin/content-pages', $this->data(['slug' => 'another', 'is_landing' => true, 'custom_domain' => 'promo.example.com']))->assertSessionHasErrors('custom_domain');
    }
}
