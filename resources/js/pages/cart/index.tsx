import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    clear as clearCart,
    destroy as destroyCartItem,
    update as updateCartItem,
} from '@/actions/App/Http/Controllers/CartController';
import { store as storeCheckout } from '@/actions/App/Http/Controllers/CheckoutController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ProductTypeBadge } from '@/components/product-type-badge';
import { Button } from '@/components/ui/button';
import { formatCents } from '@/lib/money';
import { cart as cartRoute, shop as shopRoute } from '@/routes';
import type { Cart, CartItem, Product } from '@/types';

// Omit the nullable product since it's definitely being loaded in the controller
type CartPageItem = Omit<CartItem, 'product'> & { product: Product };
type CartPageCart = Omit<Cart, 'items'> & { items: CartPageItem[] };

type Props = {
    cart: CartPageCart;
    total: number;
    errors?: { cart?: string; checkout?: string };
};

export default function CartIndex({ cart, total, errors }: Props) {
    const [clearOpen, setClearOpen] = useState(false);
    const [clearing, setClearing] = useState(false);
    const [checkingOut, setCheckingOut] = useState(false);

    function handleCheckout() {
        setCheckingOut(true);
        router.post(storeCheckout.url(), {}, { onFinish: () => setCheckingOut(false) });
    }

    function handleQuantityChange(item: CartItem, quantity: number) {
        if (quantity < 1) return;

        router.patch(updateCartItem(item).url, { quantity }, { preserveScroll: true });
    }

    function handleRemove(item: CartItem) {
        router.delete(destroyCartItem(item).url, { preserveScroll: true });
    }

    function handleClear() {
        setClearing(true);
        router.delete(clearCart.url(), {
            onSuccess: () => setClearOpen(false),
            onFinish: () => setClearing(false),
        });
    }

    if (cart.items.length === 0) {
        return (
            <>
                <Head title="Cart" />
                <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                    <p className="text-muted-foreground">Your cart is empty.</p>
                    <Button asChild>
                        <Link href={shopRoute.url()}>Browse the shop</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Cart" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 lg:flex-row lg:items-start">
                {/* Line items */}
                <div className="flex flex-1 flex-col gap-3">
                    {errors?.cart && (
                        <p className="text-sm text-destructive">{errors.cart}</p>
                    )}

                    {cart.items.map((item) => {
                        const imageUrl = item.product.image
                            ? `/storage/${item.product.image}`
                            : null;

                        return (
                            <div
                                key={item.id}
                                className="flex items-center gap-4 rounded-lg border p-4"
                            >
                                <div className="bg-muted h-16 w-16 shrink-0 overflow-hidden rounded-md">
                                    {imageUrl && (
                                        <img
                                            src={imageUrl}
                                            alt={item.product.name}
                                            className="h-full w-full object-contain"
                                        />
                                    )}
                                </div>

                                <div className="flex flex-1 flex-col gap-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">{item.product.name}</span>
                                        <ProductTypeBadge type={item.product.type} />
                                    </div>
                                    <span className="text-sm text-muted-foreground">
                                        {formatCents(item.product.price)} each
                                    </span>
                                </div>

                                {/* Quantity stepper */}
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        disabled={item.quantity <= 1}
                                        onClick={() => handleQuantityChange(item, item.quantity - 1)}
                                    >
                                        −
                                    </Button>
                                    <span className="w-6 text-center text-sm tabular-nums">
                                        {item.quantity}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="h-7 w-7 p-0"
                                        onClick={() => handleQuantityChange(item, item.quantity + 1)}
                                    >
                                        +
                                    </Button>
                                </div>

                                <span className="w-20 text-right font-medium tabular-nums">
                                    {formatCents(item.product.price * item.quantity)}
                                </span>

                                <button
                                    onClick={() => handleRemove(item)}
                                    className="text-sm text-muted-foreground transition-colors hover:text-destructive"
                                >
                                    Remove
                                </button>
                            </div>
                        );
                    })}

                    <div className="flex justify-start">
                        <button
                            onClick={() => setClearOpen(true)}
                            className="text-sm text-muted-foreground transition-colors hover:text-destructive"
                        >
                            Clear cart
                        </button>
                    </div>
                </div>

                {/* Order summary */}
                <div className="w-full rounded-lg border p-6 lg:w-72">
                    <h2 className="mb-4 font-semibold">Order Summary</h2>
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Subtotal</span>
                        <span className="font-medium tabular-nums">{formatCents(total)}</span>
                    </div>
                    <div className="mt-4">
                        <Button
                            className="w-full"
                            disabled={checkingOut}
                            onClick={handleCheckout}
                        >
                            {checkingOut ? 'Redirecting…' : 'Proceed to Checkout'}
                        </Button>
                        {errors?.checkout && (
                            <p className="mt-2 text-center text-xs text-destructive">
                                {errors.checkout}
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={clearOpen}
                onOpenChange={setClearOpen}
                title="Clear Cart"
                description="All items will be removed from your cart."
                confirmLabel="Clear cart"
                processing={clearing}
                onConfirm={handleClear}
            />
        </>
    );
}

CartIndex.layout = {
    breadcrumbs: [{ title: 'Cart', href: cartRoute.url() }],
};
