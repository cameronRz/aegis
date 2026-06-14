import { Badge } from '@/components/ui/badge';
import type { Role } from '@/types';

export const roleConfig: Record<
    Role,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    site_admin: { label: 'Site Admin', variant: 'destructive' },
    admin: { label: 'Admin', variant: 'default' },
    user: { label: 'User', variant: 'outline' },
};

export function RoleBadge({ role }: { role: Role }) {
    const { label, variant } = roleConfig[role];

    return <Badge variant={variant}>{label}</Badge>;
}
