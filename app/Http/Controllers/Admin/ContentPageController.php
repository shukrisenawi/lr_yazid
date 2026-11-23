<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContentPageRequest;
use App\Models\ContentPage;
use App\Services\ContentPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published']]);

        return Inertia::render('admin/content/Index', [
            'pages' => ContentPage::select(['id', 'title', 'slug', 'status', 'version', 'show_in_footer', 'is_landing', 'custom_domain', 'updated_at'])
                ->when($filters['search'] ?? null, fn ($query, $value) => $query->where('title', 'like', '%'.$value.'%'))
                ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
                ->latest('updated_at')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'publicBaseUrl' => rtrim(config('app.url'), '/'),
            'documentRoots' => [
                'standard' => public_path(),
                'landing' => base_path('landing-public'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/content/Edit', ['contentPage' => null, 'revisions' => [], 'publicBaseUrl' => rtrim(config('app.url'), '/')]);
    }

    public function edit(ContentPage $contentPage): Response
    {
        return Inertia::render('admin/content/Edit', [
            'contentPage' => $contentPage,
            'publicBaseUrl' => rtrim(config('app.url'), '/'),
            'revisions' => DB::table('content_page_revisions')->join('users', 'users.id', '=', 'content_page_revisions.admin_id')
                ->where('content_page_id', $contentPage->id)->orderByDesc('version')->limit(20)
                ->get(['version', 'users.name as administrator', 'content_page_revisions.created_at']),
        ]);
    }

    public function store(ContentPageRequest $request, ContentPageService $service): RedirectResponse
    {
        $page = $service->save(null, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.saved')]);

        return to_route('admin.content-pages.edit', $page);
    }

    public function update(ContentPageRequest $request, ContentPage $contentPage, ContentPageService $service): RedirectResponse
    {
        $service->save($contentPage, $request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('content.saved')]);

        return back();
    }
}
