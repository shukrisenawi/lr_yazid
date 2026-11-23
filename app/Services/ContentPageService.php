<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentPageService
{
    /** @param array<string, mixed> $data */
    public function save(?ContentPage $page, User $admin, array $data): ContentPage
    {
        abort_unless($admin->role === UserRole::Admin, 403);
        $content = app(RichTextService::class)->sanitize($data['content'] ?? '');
        if ($data['status'] === 'published' && trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}") === '') {
            throw ValidationException::withMessages(['content' => __('content.empty')]);
        }

        return DB::transaction(function () use ($page, $admin, $data, $content) {
            $page = $page ? ContentPage::whereKey($page->id)->lockForUpdate()->firstOrFail() : new ContentPage;
            if ($page->exists && $page->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => __('content.stale')]);
            }
            $page->fill(collect($data)->only(['title', 'slug', 'seo_title', 'meta_description', 'status', 'show_in_footer', 'is_landing', 'custom_domain'])->all());
            if (! $page->is_landing) {
                $page->custom_domain = null;
            }
            $page->content = $content;
            if ($page->exists && ! $page->isDirty()) {
                return $page;
            }
            $page->version = $page->exists ? $page->version + 1 : 1;
            $page->save();
            DB::table('content_page_revisions')->insert([
                'content_page_id' => $page->id, 'admin_id' => $admin->id, 'version' => $page->version,
                'snapshot' => json_encode($page->only(['title', 'slug', 'content', 'seo_title', 'meta_description', 'status', 'show_in_footer', 'is_landing', 'custom_domain']), JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $page;
        }, 3);
    }
}
