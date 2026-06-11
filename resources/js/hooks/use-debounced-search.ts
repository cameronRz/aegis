import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';

/**
 * Waits until the user stops typing for `delay` ms before firing a request.
 * The useEffect cleanup cancels the pending timer on each keystroke so only
 * the final value after a pause hits the server. The equality guard prevents
 * a request on initial mount when state already matches the server's value.
 */
export function useDebouncedSearch(
    serverValue: string | undefined,
    route: string,
    delay = 300,
): [string, Dispatch<SetStateAction<string>>] {
    const [search, setSearch] = useState(serverValue ?? '');

    useEffect(() => {
        if (search === (serverValue ?? '')) return;

        const timer = setTimeout(() => {
            router.get(route, { search: search || undefined }, { preserveState: true, replace: true });
        }, delay);

        return () => clearTimeout(timer);
    }, [search, serverValue, route, delay]);

    return [search, setSearch];
}
