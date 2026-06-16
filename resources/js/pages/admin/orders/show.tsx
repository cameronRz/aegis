import { Head, Link } from '@inertiajs/react';
import { OrderItemsTable } from '@/components/order-items-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { orderStatusConfig } from '@/lib/order-status';
import { orders as adminOrdersRoute } from '@/routes/admin';
import type { Order, OrderItem, User } from '@/types';

type Props = {
    order: Order & { items: OrderItem[]; user: User | null };
};

export default function AdminOrderShow({ order }: Props) {
    const { label, variant } = orderStatusConfig[order.status];

    return (
        <>
            <Head title={`Order ${order.order_number}`} />
            <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-center gap-3">
                    <h1 className="font-mono text-xl font-semibold">{order.order_number}</h1>
                    <Badge variant={variant}>{label}</Badge>
                    <span className="text-muted-foreground ml-auto text-sm">
                        {new Date(order.created_at).toLocaleDateString()}
                    </span>
                </div>

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

                <OrderItemsTable items={order.items} total={order.total} />

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
