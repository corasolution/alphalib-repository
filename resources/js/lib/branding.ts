import { usePage } from '@inertiajs/react';

export interface SystemBranding {
    name: string;
    logoUrl: string | null;
}

/**
 * The system's admin-editable name and logo, from the props shared by HandleInertiaRequests.
 *
 * Falls back to the bundled default rather than rendering an empty string: this is used in the
 * sidebar and on the login screen, and a blank space where the product name belongs looks like a
 * broken page rather than an unconfigured one.
 */
export function useSystemBranding(): SystemBranding {
    const site = usePage().props.site as SystemBranding | undefined;

    return {
        name: site?.name || 'Alpha Journal',
        logoUrl: site?.logoUrl ?? null,
    };
}
