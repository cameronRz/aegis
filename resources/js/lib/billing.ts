import type { BillingInterval } from '@/types';

export const intervalLabels: Record<BillingInterval, string> = {
    weekly: 'week',
    monthly: 'month',
    yearly: 'year',
};
