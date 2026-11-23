<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = ContentPage::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return $this->render($page);
    }

    public function landing(string $slug): Response
    {
        return $this->render(ContentPage::where('slug', $slug)->where('is_landing', true)->where('status', 'published')->firstOrFail());
    }

    public function render(ContentPage $page): Response
    {
        return Inertia::render($page->is_landing ? 'landing/Show' : 'public/ContentPage', [
            'contentPage' => $page->only(['title', 'slug', 'content', 'seo_title', 'meta_description', 'updated_at']),
        ]);
    }
}
