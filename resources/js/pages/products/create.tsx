import { Head, useForm } from '@inertiajs/react';
import { ProductFormFields } from './product-form-fields';
import type { ProductCategory, ProductFormData } from './product-form-fields';
import { store as storeProduct } from '@/actions/App/Http/Controllers/ProductController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { products as adminProductsRoute } from '@/routes/admin';


type Props = {
    categories: ProductCategory[];
};

export default function ProductCreate({ categories }: Props) {
    const { data, setData, post, processing, errors } = useForm<ProductFormData>({
        name: '',
        sku: '',
        description: '',
        category_id: null,
        type: 'physical',
        price: 0,
        price_type: 'one_time',
        billing_interval: null,
        billing_interval_count: null,
        trial_period_days: null,
        stock_quantity: null,
        track_inventory: false,
        is_active: true,
        image: null,
        remove_image: false,
    });

    return (
        <>
            <Head title="Create Product" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(storeProduct.url(), { forceFormData: true });
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Product Details</CardTitle>
                        <CardDescription>
                            New products are automatically positioned at the end of their category.
                            Sort order can be adjusted later.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ProductFormFields
                            data={data}
                            setData={setData}
                            errors={errors}
                            categories={categories}
                        />
                        <Separator />
                        <div className="flex items-center gap-4 pt-2">
                            <Button type="submit" disabled={processing}>
                                Create Product
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

ProductCreate.layout = {
    breadcrumbs: [
        { title: 'Products', href: adminProductsRoute.url() },
        { title: 'Create Product' },
    ],
};
