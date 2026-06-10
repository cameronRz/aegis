import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { destroy as destroyProduct, edit as editProduct } from '@/actions/App/Http/Controllers/ProductController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCents } from '@/lib/money';
import { products as adminProductsRoute } from '@/routes/admin';
import type { BillingInterval, Product, ProductType } from '@/types';

type Props = {
    product: Product;
    imageUrl: string | null;
    canEdit: boolean;
    canDelete: boolean;
};

const typeConfig: Record<
    ProductType,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    physical: { label: 'Physical', variant: 'default' },
    digital: { label: 'Digital', variant: 'secondary' },
    subscription: { label: 'Subscription', variant: 'outline' },
};

const intervalLabels: Record<BillingInterval, string> = {
    weekly: 'week',
    monthly: 'month',
    yearly: 'year',
};

export default function ProductShow({ product, imageUrl, canEdit, canDelete }: Props) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const { label: typeLabel, variant: typeVariant } = typeConfig[product.type];
    const isSubscription = product.type === 'subscription';
    const isPhysical = product.type === 'physical';

    function handleDelete() {
        setDeleting(true);
        router.delete(destroyProduct(product).url, {
            onSuccess: () => setDeleteOpen(false),
            onFinish: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title={product.name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Product info card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-4">
                            {imageUrl && (
                                <img
                                    src={imageUrl}
                                    alt={product.name}
                                    className="h-20 w-20 shrink-0 rounded-md object-cover"
                                />
                            )}
                            <div className="flex flex-1 items-start justify-between gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <CardTitle className="text-xl">{product.name}</CardTitle>
                                    <p className="text-sm text-muted-foreground">{product.sku}</p>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={typeVariant}>{typeLabel}</Badge>
                                        <Badge variant={product.is_active ? 'default' : 'secondary'}>
                                            {product.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                        {product.category && (
                                            <span className="text-sm text-muted-foreground">
                                                {product.category.name}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                {canEdit && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={editProduct(product).url}>Edit</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    {product.description && (
                        <CardContent>
                            <p className="text-sm">{product.description}</p>
                        </CardContent>
                    )}
                    {canDelete && (
                        <CardContent className="pt-0">
                            <button
                                onClick={() => setDeleteOpen(true)}
                                className="text-sm text-muted-foreground transition-colors hover:text-destructive"
                            >
                                Delete product
                            </button>
                        </CardContent>
                    )}
                </Card>

                {/* Pricing & details card */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pricing & Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-[160px_1fr] gap-x-4 gap-y-3 text-sm">
                            <dt className="text-muted-foreground">Price</dt>
                            <dd>{formatCents(product.price)}</dd>

                            {isSubscription && (
                                <>
                                    <dt className="text-muted-foreground">Billing</dt>
                                    <dd>
                                        Every{' '}
                                        {product.billing_interval_count && product.billing_interval_count > 1
                                            ? `${product.billing_interval_count} ${intervalLabels[product.billing_interval!]}s`
                                            : intervalLabels[product.billing_interval!]}
                                    </dd>

                                    <dt className="text-muted-foreground">Trial period</dt>
                                    <dd>
                                        {product.trial_period_days
                                            ? `${product.trial_period_days} days`
                                            : 'None'}
                                    </dd>
                                </>
                            )}

                            {isPhysical && (
                                <>
                                    <dt className="text-muted-foreground">Track inventory</dt>
                                    <dd>{product.track_inventory ? 'Yes' : 'No'}</dd>

                                    {product.track_inventory && (
                                        <>
                                            <dt className="text-muted-foreground">Stock</dt>
                                            <dd>
                                                {product.stock_quantity !== null
                                                    ? `${product.stock_quantity} units`
                                                    : 'Unlimited'}
                                            </dd>
                                        </>
                                    )}
                                </>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent aria-describedby={undefined}>
                    <DialogTitle>Delete {product.name}</DialogTitle>
                    <Alert variant="destructive">
                        <AlertTitle>Are you sure?</AlertTitle>
                        <AlertDescription>
                            <strong>{product.name}</strong> will be permanently deleted.
                        </AlertDescription>
                    </Alert>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" disabled={deleting} onClick={handleDelete}>
                            Delete product
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductShow.layout = {
    breadcrumbs: [
        { title: 'Products', href: adminProductsRoute.url() },
        { title: 'Product Details' },
    ],
};
