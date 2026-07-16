import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * The current path + query string, for passing back to the server as an
 * explicit redirect target (e.g. cart actions) instead of relying on the
 * Referer header, which Inertia's SPA navigation doesn't always send.
 */
export function currentPath(): string {
    return window.location.pathname + window.location.search;
}
