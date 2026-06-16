import type { OrderStatus } from '@/types';

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

export const orderStatusConfig: Record<OrderStatus, { label: string; variant: BadgeVariant }> = {
    pending: { label: 'Processing', variant: 'secondary' },
    paid: { label: 'Paid', variant: 'default' },
    failed: { label: 'Failed', variant: 'destructive' },
    refunded: { label: 'Refunded', variant: 'outline' },
    expired: { label: 'Expired', variant: 'outline' },
};
