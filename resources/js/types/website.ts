export type Website = {
    website_name: string;
    logo_url: string | null;
    favicon_url: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    address: string | null;
    footer_information: string;
    social_links: { label: string; url: string }[];
};
export type Banner = {
    id: number;
    title: string;
    subtitle: string | null;
    image_alt: string | null;
    link_label: string | null;
    link_path: string | null;
    status: 'draft' | 'published';
    position: number;
    version: number;
    starts_at: string | null;
    ends_at: string | null;
};
export type PublicBanner = Pick<
    Banner,
    'id' | 'title' | 'subtitle' | 'image_alt' | 'link_label' | 'link_path'
> & { image_url: string };
