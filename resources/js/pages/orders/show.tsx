import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCents } from '@/lib/money';
import { orders as ordersRoute } from '@/routes';
import { subscriptions as subscriptionsRoute } from '@/routes';
import type { Order, OrderItem, OrderStatus } from '@/types';

type Props = {
    order: Order & { items: OrderItem[] };
};

const statusConfig: Record<OrderStatus, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    pending: { label: 'Processing', variant: 'secondary' },
    paid: { label: 'Paid', variant: 'default' },
    failed: { label: 'Failed', variant: 'destructive' },
    refunded: { label: 'Refunded', variant: 'outline' },
    expired: { label: 'Expired', variant: 'outline' },
};

const typeLabels: Record<string, string> = {
    physical: 'Physical',
    digital: 'Digital',
    subscription: 'Subscription',
};

export default function OrderShow({ order }: Props) {
    const { label, variant } = statusConfig[order.status];
    const hasSubscription = order.items.some((i) => i.product_type === 'subscription');

    return (
        <>
            <Head title={`Order ${order.order_number}`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-3">
                        <h1 className="font-mono text-xl font-semibold">{order.order_number}</h1>
                        <Badge variant={variant}>{label}</Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        Placed on {new Date(order.created_at).toLocaleDateString()}
                    </p>
                </div>

                {/* Line items */}
                <div className="rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="px-4 py-3 text-left font-medium">Item</th>
                                <th className="px-4 py-3 text-left font-medium">Type</th>
                                <th className="px-4 py-3 text-right font-medium">Unit price</th>
                                <th className="px-4 py-3 text-right font-medium">Qty</th>
                                <th className="px-4 py-3 text-right font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.items.map((item) => (
                                <tr key={item.id} className="border-b last:border-0">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{item.product_name}</div>
                                        <div className="text-muted-foreground">{item.product_sku}</div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {typeLabels[item.product_type] ?? item.product_type}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">{formatCents(item.price)}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">{item.quantity}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatCents(item.price * item.quantity)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t">
                                <td colSpan={4} className="px-4 py-3 font-semibold">
                                    Total
                                </td>
                                <td className="px-4 py-3 text-right font-semibold tabular-nums">
                                    {formatCents(order.total)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

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
