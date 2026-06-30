import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { Auth } from '@/types/auth';

export function useSupportNotifications() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const userId = auth?.user?.id;

    useEffect(() => {
        if (!userId || typeof window === 'undefined' || !window.Echo) return;

        const channel = window.Echo.private(`App.Models.User.${userId}`);

        channel.listen('NewSupportMessage', () => {
            router.reload({ only: ['unreadSupportCount', 'conversations'] });
        });

        return () => {
            window.Echo.leave(`App.Models.User.${userId}`);
        };
    }, [userId]);
}
