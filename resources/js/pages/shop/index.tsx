import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { destroy as removeFromCart, store as addToCart } from '@/actions/App/Http/Controllers/CartController';
import { DataTablePagination } from '@/components/data-table-pagination';
import { ProductTypeBadge } from '@/components/product-type-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { intervalLabels } from '@/lib/billing';
import { formatCents } from '@/lib/money';
import { shop as shopRoute } from '@/routes';
import { show as showProduct } from '@/routes/shop';
import type { BillingInterval, PaginatedData, Product } from '@/types';

type ShopCategory = { id: number; name: string; slug: string };

type Props = {
    products: PaginatedData<Product>;
    categories: ShopCategory[];
    filters: { search?: string; category?: string };
    cartItems: Record<number, number>;
};

function formatProductPrice(product: Product): string {
    const price = formatCents(product.price);

    if (product.type === 'subscription' && product.billing_interval) {
        return `${price}/${intervalLabels[product.billing_interval as BillingInterval]}`;
    }

    return price;
}

export default function ShopIndex({ products, categories, filters, cartItems }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    /**
     * Inline debounce instead of useDebouncedSearch — the shop has two coordinated
     * filters (search + category) that must be passed together in every router call.
     * The hook only carries search, so using it here would drop the category on each
     * keystroke. If a second multi-filter page appears, update the hook then.
     */
    useEffect(() => {
        if (search === (filters.search ?? '')) return;

        const timer = setTimeout(() => {
            router.get(
                shopRoute.url(),
                { search: search || undefined, category: filters.category || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search, filters.search, filters.category]);

    function handleCategoryChange(value: string) {
        router.get(
            shopRoute.url(),
            { category: value === 'all' ? undefined : value, search: search || undefined },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Shop" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Select value={filters.category ?? 'all'} onValueChange={handleCategoryChange}>
                        <SelectTrigger className="w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Categories</SelectItem>
                            {categories.map((cat) => (
                                <SelectItem key={cat.id} value={cat.slug}>
                                    {cat.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        placeholder="Search products..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                </div>

                {products.data.length ? (
                    <div className="grid gap-3 grid-cols-[repeat(auto-fill,minmax(220px,1fr))]">
                        {products.data.map((product) => {
                            const imageUrl = product.image ? `/storage/${product.image}` : null;

                            return (
                                <Card
                                    key={product.id}
                                    className="flex flex-col cursor-pointer overflow-hidden transition-shadow hover:shadow-md"
                                    onClick={() => router.visit(showProduct(product).url)}
                                >
                                    <div className="bg-muted aspect-4/3 w-full">
                                        {imageUrl && (
                                            <img
                                                src={imageUrl}
                                                alt={product.name}
                                                className="h-full w-full object-contain"
                                            />
                                        )}
                                    </div>
                                    <CardContent className="flex flex-1 flex-col gap-2 p-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <p className="font-medium leading-tight">{product.name}</p>
                                            <ProductTypeBadge type={product.type} />
                                        </div>
                                        {product.category && (
                                            <p className="text-muted-foreground text-sm">
                                                {product.category.name}
                                            </p>
                                        )}
                                        <p className="mt-auto pt-2 font-semibold">
                                            {formatProductPrice(product)}
                                        </p>
                                        {cartItems[product.id] == null ? (
                                            <Button
                                                size="sm"
                                                className="w-full"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    router.post(
                                                        addToCart.url(),
                                                        { product_id: product.id },
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => toast('Added to cart'),
                                                        },
                                                    );
                                                }}
                                            >
                                                Add to Cart
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                className="w-full"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    router.delete(removeFromCart(cartItems[product.id]).url, {
                                                        preserveScroll: true,
                                                        onSuccess: () => toast('Removed from cart'),
                                                    });
                                                }}
                                            >
                                                Remove from Cart
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <div className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        No products found.
                    </div>
                )}

                <DataTablePagination paginatedData={products} />
            </div>
        </>
    );
}

ShopIndex.layout = {
    breadcrumbs: [{ title: 'Shop', href: shopRoute.url() }],
};
