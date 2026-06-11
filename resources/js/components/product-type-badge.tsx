import { Badge } from '@/components/ui/badge';
import type { ProductType } from '@/types';

export const productTypeConfig: Record<
    ProductType,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    physical: { label: 'Physical', variant: 'default' },
    digital: { label: 'Digital', variant: 'secondary' },
    subscription: { label: 'Subscription', variant: 'outline' },
};

export function ProductTypeBadge({ type }: { type: ProductType }) {
    const { label, variant } = productTypeConfig[type];

    return <Badge variant={variant}>{label}</Badge>;
}
