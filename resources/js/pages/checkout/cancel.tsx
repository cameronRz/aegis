import { Head, Link } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cart as cartRoute } from '@/routes';

export default function CheckoutCancel() {
    return (
        <>
            <Head title="Checkout Cancelled" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <XCircle className="text-muted-foreground h-12 w-12" />
                <h1 className="text-2xl font-semibold">Checkout cancelled</h1>
                <p className="text-muted-foreground max-w-sm">
                    Your cart has been kept — you can continue shopping or try checking out again.
                </p>
                <Button asChild>
                    <Link href={cartRoute.url()}>Back to cart</Link>
                </Button>
            </div>
        </>
    );
}

CheckoutCancel.layout = {
    breadcrumbs: [{ title: 'Checkout Cancelled' }],
};
