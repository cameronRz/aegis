import { Head, useForm } from '@inertiajs/react';
import { ProductFormFields } from './product-form-fields';
import type { ProductCategory, ProductFormData } from './product-form-fields';
import { update as updateProduct } from '@/actions/App/Http/Controllers/ProductController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { products as adminProductsRoute } from '@/routes/admin';
import type { Product } from '@/types';


type Props = {
    product: Product;
    categories: ProductCategory[];
    imageUrl: string | null;
};

export default function ProductEdit({ product, categories, imageUrl }: Props) {
    const { data, setData, patch, processing, errors } = useForm<ProductFormData>({
        name: product.name,
        sku: product.sku,
        description: product.description,
        category_id: product.category_id,
        type: product.type,
        price: product.price,
        price_type: product.price_type,
        billing_interval: product.billing_interval,
        billing_interval_count: product.billing_interval_count,
        trial_period_days: product.trial_period_days,
        stock_quantity: product.stock_quantity,
        track_inventory: product.track_inventory,
        is_active: product.is_active,
        image: null,
        remove_image: false,
    });

    return (
        <>
            <Head title="Edit Product" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    patch(updateProduct(product).url, { forceFormData: true });
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Product</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ProductFormFields
                            data={data}
                            setData={setData}
                            errors={errors}
                            categories={categories}
                            existingImageUrl={imageUrl}
                        />
                        <Separator />
                        <div className="flex items-center gap-4 pt-2">
                            <Button type="submit" disabled={processing}>
                                Save Changes
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

ProductEdit.layout = {
    breadcrumbs: [
        { title: 'Products', href: adminProductsRoute.url() },
        { title: 'Edit Product' },
    ],
};
