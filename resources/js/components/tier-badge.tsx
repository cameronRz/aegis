import { Badge } from '@/components/ui/badge';
import type { Tier } from '@/types';

export const tierConfig: Record<
    Tier,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    site_admin: { label: 'Site Admin', variant: 'destructive' },
    admin: { label: 'Admin', variant: 'default' },
    user: { label: 'User', variant: 'outline' },
};

export function TierBadge({ tier }: { tier: Tier }) {
    const { label, variant } = tierConfig[tier];

    return <Badge variant={variant}>{label}</Badge>;
}
