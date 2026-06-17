import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCents } from '@/lib/money';
import { orderStatusConfig } from '@/lib/order-status';
import { dashboard } from '@/routes';
import { orders as adminOrdersRoute } from '@/routes/admin';
import type { Order, User } from '@/types';

type AdminOrderRow = Order & { items_count: number; user: User | null };

type Props = {
    revenueAllTime: number | null;
    revenueMtd: number | null;
    activeSubscriptions: number | null;
    newClientsThisMonth: number | null;
    recentOrders: AdminOrderRow[] | null;
};

export default function AdminDashboard({ revenueAllTime, revenueMtd, activeSubscriptions, newClientsThisMonth, recentOrders }: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Total Revenue</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {revenueAllTime == null ? (
                                <Skeleton className="h-8 w-32" />
                            ) : (
                                <p className="text-2xl font-bold tabular-nums">{formatCents(revenueAllTime)}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Revenue This Month</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {revenueMtd == null ? (
                                <Skeleton className="h-8 w-32" />
                            ) : (
                                <p className="text-2xl font-bold tabular-nums">{formatCents(revenueMtd)}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">Active Subscriptions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {activeSubscriptions == null ? (
                                <Skeleton className="h-8 w-16" />
                            ) : (
                                <p className="text-2xl font-bold">{activeSubscriptions}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">New Clients This Month</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {newClientsThisMonth == null ? (
                                <Skeleton className="h-8 w-16" />
                            ) : (
                                <p className="text-2xl font-bold">{newClientsThisMonth}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Orders</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="px-4 py-3 text-left font-medium">Order</th>
                                    <th className="px-4 py-3 text-left font-medium">Client</th>
                                    <th className="px-4 py-3 text-left font-medium">Date</th>
                                    <th className="px-4 py-3 text-left font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentOrders == null ? (
                                    Array.from({ length: 5 }).map((_, i) => (
                                        <tr key={i} className="border-b last:border-0">
                                            <td className="px-4 py-3"><Skeleton className="h-4 w-24" /></td>
                                            <td className="px-4 py-3"><Skeleton className="h-4 w-32" /></td>
                                            <td className="px-4 py-3"><Skeleton className="h-4 w-20" /></td>
                                            <td className="px-4 py-3"><Skeleton className="h-5 w-16" /></td>
                                            <td className="px-4 py-3 text-right"><Skeleton className="ml-auto h-4 w-16" /></td>
                                        </tr>
                                    ))
                                ) : recentOrders.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                            No orders yet.
                                        </td>
                                    </tr>
                                ) : (
                                    recentOrders.map((order) => {
                                        const { label, variant } = orderStatusConfig[order.status];

                                        return (
                                            <tr key={order.id} className="border-b last:border-0">
                                                <td className="px-4 py-3 font-mono">{order.order_number}</td>
                                                <td className="px-4 py-3">
                                                    {order.user ? (
                                                        order.user.full_name
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">
                                                    {new Date(order.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={variant}>{label}</Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums">
                                                    {formatCents(order.total)}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                        <div className="border-t px-4 py-3">
                            <Link
                                href={adminOrdersRoute.url()}
                                className="text-muted-foreground hover:text-foreground text-sm transition-colors"
                            >
                                View all orders →
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard.url() }],
};
