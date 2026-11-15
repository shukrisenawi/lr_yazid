export type ContentPage = {
    id: number;
    title: string;
    slug: string;
    content: string;
    seo_title: string | null;
    meta_description: string | null;
    status: 'draft' | 'published';
    show_in_footer: boolean;
    version: number;
    updated_at: string;
};
