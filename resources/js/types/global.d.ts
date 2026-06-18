import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';
import type { Auth } from '@/types/auth';

declare global {
    interface Window {
        Echo: Echo;
        Pusher: typeof Pusher;
    }
}

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
