<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function show(string $slug): Response
    {
        return Inertia::render('public/ContentPage', [
            'contentPage' => ContentPage::where('slug', $slug)->where('status', 'published')
                ->firstOrFail(['title', 'slug', 'content', 'seo_title', 'meta_description', 'updated_at']),
        ]);
    }
}
