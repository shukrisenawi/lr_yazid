export type ContentPage = {
    id: number;
    title: string;
    slug: string;
    content: string;
    seo_title: string | null;
    meta_description: string | null;
    status: 'draft' | 'published';
    show_in_footer: boolean;
    is_landing?: boolean;
    custom_domain?: string | null;
    version: number;
    updated_at: string;
};
