import { Head, useForm } from '@inertiajs/react';
import { CategoryFormFields } from './category-form-fields';
import type { CategoryFormData, ParentCategory } from './category-form-fields';
import { store as storeCategory } from '@/actions/App/Http/Controllers/CategoryController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { categories as adminCategoriesRoute } from '@/routes/admin';


type Props = {
    parentCategories: ParentCategory[];
};

export default function CategoryCreate({ parentCategories }: Props) {
    const { data, setData, post, processing, errors } = useForm<CategoryFormData>({
        name: '',
        slug: '',
        parent_id: null,
        is_active: true,
    });

    return (
        <>
            <Head title="Create Category" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(storeCategory.url());
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Category Details</CardTitle>
                        <CardDescription>
                            New categories are automatically positioned after existing ones within
                            their parent. Sort order can be adjusted later.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <CategoryFormFields
                            data={data}
                            setData={setData}
                            errors={errors}
                            parentCategories={parentCategories}
                        />
                    </CardContent>
                </Card>

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        Create Category
                    </Button>
                </div>
            </form>
        </>
    );
}

CategoryCreate.layout = {
    breadcrumbs: [
        { title: 'Categories', href: adminCategoriesRoute.url() },
        { title: 'Create Category' },
    ],
};
