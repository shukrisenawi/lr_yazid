export function websiteTitle(
    title: string,
    website: unknown,
    fallback: string,
): string {
    const name =
        website &&
        typeof website === 'object' &&
        'website_name' in website &&
        typeof website.website_name === 'string' &&
        website.website_name
            ? website.website_name
            : fallback;
    return title ? `${title} - ${name}` : name;
}
