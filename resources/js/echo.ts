import { router } from '@inertiajs/react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// WebSocket connections are browser-only; this guard prevents SSR from crashing on `window`
if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    // Inertia v3 removed Axios, so Echo can't auto-inject the socket ID. Without this,
    // toOthers() on the server has no socket to exclude and broadcasts back to the sender.
    router.on('before', (event) => {
        const socketId = window.Echo?.socketId();
        if (socketId) {
            event.detail.visit.headers['X-Socket-ID'] = socketId;
        }
    });
}
