import { useEffect, useState } from 'react';

interface Props {
    iso: string;
    options?: Intl.DateTimeFormatOptions;
}

// Formats a date on the client only. toLocaleString/toLocaleDateString/toLocaleTimeString
// differ between server (UTC) and browser (local TZ), which causes React 19 hydration errors
// when called during SSR. Starting with an empty string ensures server and client agree on
// the initial render; the effect fills in the local-timezone value after hydration.
export function ClientDate({ iso, options }: Props) {
    const [formatted, setFormatted] = useState('');

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setFormatted(new Intl.DateTimeFormat(undefined, options).format(new Date(iso)));
    }, [iso, options]);

    return <>{formatted}</>;
}
