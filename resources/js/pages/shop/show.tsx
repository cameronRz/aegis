import { Head, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { store as addToCart } from '@/actions/App/Http/Controllers/CartController';
import { ProductTypeBadge } from '@/components/product-type-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { intervalLabels } from '@/lib/billing';
import { formatCents } from '@/lib/money';
import { shop as shopRoute } from '@/routes';
import type { BillingInterval, Product } from '@/types';

type Props = {
    product: Product;
    imageUrl: string | null;
};

function formatProductPrice(product: Product): string {
    const price = formatCents(product.price);

    if (product.type === 'subscription' && product.billing_interval) {
        return `${price}/${intervalLabels[product.billing_interval as BillingInterval]}`;
    }

    return price;
}

function stockLabel(product: Product): string | null {
    if (!product.track_inventory) return null;

    return product.stock_quantity && product.stock_quantity > 0 ? 'In stock' : 'Out of stock';
}

export default function ShopShow({ product, imageUrl }: Props) {
    const isSubscription = product.type === 'subscription';
    const isPhysical = product.type === 'physical';
    const stock = stockLabel(product);

    return (
        <>
            <Head title={product.name} />
            <div className="flex h-full flex-1 flex-col p-4">
                <div className="grid gap-8 lg:grid-cols-2">
                    {imageUrl ? (
                        <img
                            src={imageUrl}
                            alt={product.name}
                            className="aspect-square w-full rounded-lg object-cover"
                        />
                    ) : (
                        <div className="bg-muted aspect-square w-full rounded-lg" />
                    )}

                    <div className="flex flex-col gap-4">
                        <div className="flex items-start justify-between gap-4">
                            <h1 className="text-2xl font-semibold leading-tight">{product.name}</h1>
                            <ProductTypeBadge type={product.type} />
                        </div>

                        {product.category && (
                            <p className="text-muted-foreground text-sm">{product.category.name}</p>
                        )}

                        <p className="text-2xl font-bold">{formatProductPrice(product)}</p>

                        {stock && (
                            <Badge variant={stock === 'In stock' ? 'default' : 'secondary'}>
                                {stock}
                            </Badge>
                        )}

                        <Button
                            className="w-full sm:w-auto"
                            onClick={() =>
                                router.post(
                                    addToCart.url(),
                                    { product_id: product.id },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => toast('Added to cart'),
                                    },
                                )
                            }
                        >
                            Add to Cart
                        </Button>

                        {product.description && (
                            <p className="text-sm leading-relaxed">{product.description}</p>
                        )}

                        {isSubscription && (
                            <dl className="grid grid-cols-[140px_1fr] gap-x-4 gap-y-2 text-sm">
                                <dt className="text-muted-foreground">Billing</dt>
                                <dd>
                                    Every{' '}
                                    {product.billing_interval_count && product.billing_interval_count > 1
                                        ? `${product.billing_interval_count} ${intervalLabels[product.billing_interval as BillingInterval]}s`
                                        : intervalLabels[product.billing_interval as BillingInterval]}
                                </dd>

                                {product.trial_period_days ? (
                                    <>
                                        <dt className="text-muted-foreground">Free trial</dt>
                                        <dd>{product.trial_period_days} days</dd>
                                    </>
                                ) : null}
                            </dl>
                        )}

                        {isPhysical && product.track_inventory && product.stock_quantity !== null && (
                            <p className="text-muted-foreground text-sm">
                                {product.stock_quantity} unit{product.stock_quantity !== 1 ? 's' : ''} available
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ShopShow.layout = {
    breadcrumbs: [
        { title: 'Shop', href: shopRoute.url() },
        { title: 'Product Details' },
    ],
};
