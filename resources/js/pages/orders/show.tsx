import { Head, Link } from '@inertiajs/react';
import { OrderItemsTable } from '@/components/order-items-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { orderStatusConfig } from '@/lib/order-status';
import { orders as ordersRoute, subscriptions as subscriptionsRoute } from '@/routes';
import type { Order, OrderItem } from '@/types';

type Props = {
    order: Order & { items: OrderItem[] };
};

export default function OrderShow({ order }: Props) {
    const { label, variant } = orderStatusConfig[order.status];
    const hasSubscription = order.items.some((i) => i.product_type === 'subscription');

    return (
        <>
            <Head title={`Order ${order.order_number}`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-3">
                        <h1 className="font-mono text-xl font-semibold">{order.order_number}</h1>
                        <Badge variant={variant}>{label}</Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Placed on {new Date(order.created_at).toLocaleDateString()}
                    </p>
                </div>

                <OrderItemsTable items={order.items} total={order.total} />

                {hasSubscription && (
                    <div className="text-muted-foreground text-sm">
                        This order includes a subscription.{' '}
                        <Link
                            href={subscriptionsRoute.url()}
                            className="text-foreground underline underline-offset-4"
                        >
                            View your subscriptions →
                        </Link>
                    </div>
                )}

                <div>
                    <Button variant="outline" asChild>
                        <Link href={ordersRoute.url()}>← Back to orders</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [
        { title: 'Orders', href: ordersRoute.url() },
        { title: 'Order Details' },
    ],
};
