/**
 * Mirrors Laravel's Str::slug closely enough for a live preview.
 *
 * The server still has the final say — SitePage::uniqueSlugFor() runs on save and resolves
 * collisions. This exists so an author can see the URL their page will get while they are still
 * typing the title, rather than discovering it afterwards.
 *
 * Returns an empty string when the title has nothing sluggable in it (a title written entirely
 * in Khmer, for instance). The caller shows that state honestly rather than inventing a slug the
 * server would not agree with.
 */
export function slugify(value: string): string {
    return (
        value
            .normalize('NFKD')
            // Strip combining marks so "é" becomes "e" rather than disappearing.
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
    );
}
