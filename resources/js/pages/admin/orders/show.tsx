import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCents } from '@/lib/money';
import { orders as adminOrdersRoute } from '@/routes/admin';
import type { Order, OrderItem, OrderStatus, User } from '@/types';

type Props = {
    order: Order & { items: OrderItem[]; user: User | null };
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

export default function AdminOrderShow({ order }: Props) {
    const { label, variant } = statusConfig[order.status];

    return (
        <>
            <Head title={`Order ${order.order_number}`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <h1 className="font-mono text-xl font-semibold">{order.order_number}</h1>
                    <Badge variant={variant}>{label}</Badge>
                    <span className="text-muted-foreground ml-auto text-sm">
                        {new Date(order.created_at).toLocaleDateString()}
                    </span>
                </div>

                {/* Client */}
                {order.user && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Client</CardTitle>
                            <CardDescription>{order.user.email}</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <p className="font-medium">{order.user.full_name}</p>
                        </CardContent>
                    </Card>
                )}

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

                <div>
                    <Button variant="outline" asChild>
                        <Link href={adminOrdersRoute.url()}>← Back to orders</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

AdminOrderShow.layout = {
    breadcrumbs: [
        { title: 'Orders', href: adminOrdersRoute.url() },
        { title: 'Order Details' },
    ],
};
