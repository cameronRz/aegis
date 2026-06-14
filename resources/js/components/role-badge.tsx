import { Badge } from '@/components/ui/badge';
import type { Tier } from '@/types';

export const roleConfig: Record<
    Tier,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    site_admin: { label: 'Site Admin', variant: 'destructive' },
    admin: { label: 'Admin', variant: 'default' },
    user: { label: 'User', variant: 'outline' },
};

export function RoleBadge({ role }: { role: Tier }) {
    const { label, variant } = roleConfig[role];

    return <Badge variant={variant}>{label}</Badge>;
}
