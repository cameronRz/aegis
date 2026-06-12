import { Head, Link } from '@inertiajs/react';
import { CheckCircle, Clock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCents } from '@/lib/money';
import type { Order, OrderStatus } from '@/types';

type Props = {
    order: Order;
};

const statusConfig: Record<
    OrderStatus,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    pending: { label: 'Processing', variant: 'secondary' },
    paid: { label: 'Paid', variant: 'default' },
    failed: { label: 'Failed', variant: 'destructive' },
    refunded: { label: 'Refunded', variant: 'outline' },
    expired: { label: 'Expired', variant: 'outline' },
};

export default function CheckoutSuccess({ order }: Props) {
    const isPending = order.status === 'pending';
    const { label, variant } = statusConfig[order.status];

    return (
        <>
            <Head title="Order Confirmation" />
            <div className="mx-auto max-w-2xl p-6">
                {isPending ? (
                    <div className="flex flex-col items-center gap-4 py-12 text-center">
                        <Clock className="text-muted-foreground h-12 w-12 animate-pulse" />
                        <h1 className="text-2xl font-semibold">Confirming your payment…</h1>
                        <p className="text-muted-foreground">
                            Your payment is being processed. This may take a few moments.
                        </p>
                        <div className="w-full space-y-3 pt-4">
                            <Skeleton className="h-16 w-full" />
                            <Skeleton className="h-16 w-full" />
                            <Skeleton className="ml-auto h-6 w-32" />
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col items-center gap-3 py-6 text-center">
                            <CheckCircle className="h-12 w-12 text-green-600" />
                            <h1 className="text-2xl font-semibold">Order confirmed!</h1>
                            <p className="text-muted-foreground">
                                Order{' '}
                                <span className="text-foreground font-mono font-medium">
                                    {order.order_number}
                                </span>
                            </p>
                            <Badge variant={variant}>{label}</Badge>
                        </div>

                        <div className="rounded-lg border">
                            {order.items?.map((item, i) => (
                                <div
                                    key={item.id}
                                    className={`flex items-center justify-between px-4 py-3 text-sm${
                                        i < (order.items?.length ?? 0) - 1 ? ' border-b' : ''
                                    }`}
                                >
                                    <div className="flex flex-col gap-0.5">
                                        <span className="font-medium">{item.product_name}</span>
                                        <span className="text-muted-foreground">
                                            {item.product_sku} × {item.quantity}
                                        </span>
                                    </div>
                                    <span className="tabular-nums">
                                        {formatCents(item.price * item.quantity)}
                                    </span>
                                </div>
                            ))}
                            <div className="flex items-center justify-between border-t px-4 py-3 font-semibold">
                                <span>Total</span>
                                <span className="tabular-nums">{formatCents(order.total)}</span>
                            </div>
                        </div>

                        <div className="flex justify-center">
                            <Button asChild variant="outline">
                                <Link href="/orders">View order history</Link>
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CheckoutSuccess.layout = {
    breadcrumbs: [{ title: 'Order Confirmation' }],
};
