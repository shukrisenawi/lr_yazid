export type Category = {
    id: number;
    name: string;
    slug: string;
    products_count?: number;
};
export type ProductImage = {
    id: number;
    url: string;
    alt: string;
    position: number;
};
export type ProductVariant = {
    id?: number;
    name: string;
    sku: string;
    price_sen: number;
    stock: number;
    is_active: boolean;
    product_image_id?: number | null;
    attributes?: { name: string; value: string }[];
};
export type Product = {
    id: number;
    name: string;
    slug: string;
    sku: string;
    category_id: number | null;
    category?: Category;
    type: 'physical' | 'membership';
    short_description: string;
    description: string;
    price_sen: number;
    current_price_sen: number;
    sale_price_sen: number | null;
    sale_starts_at: string | null;
    sale_ends_at: string | null;
    stock: number;
    weight_grams: number;
    status: 'draft' | 'active' | 'inactive' | 'archived';
    is_featured: boolean;
    seo_title: string;
    meta_description: string;
    images: ProductImage[];
    variants?: ProductVariant[];
    variants_count?: number;
};
export type ProductCardData = Pick<
    Product,
    | 'id'
    | 'name'
    | 'slug'
    | 'type'
    | 'price_sen'
    | 'current_price_sen'
    | 'stock'
    | 'images'
    | 'variants_count'
> & {
    category?: Category | null;
};
export type Paginated<T> = {
    data: T[];
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
export const money = (sen: number) =>
    new Intl.NumberFormat('en-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(sen / 100);
