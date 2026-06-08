import { Head, useForm } from '@inertiajs/react';

import { update as updateCategory } from '@/actions/App/Http/Controllers/CategoryController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { categories as adminCategoriesRoute } from '@/routes/admin';
import type { Category } from '@/types';

import { CategoryFormFields } from './category-form-fields';
import type { CategoryFormData, ParentCategory } from './category-form-fields';

type Props = {
    category: Category;
    parentCategories: ParentCategory[];
};

export default function CategoryEdit({ category, parentCategories }: Props) {
    const { data, setData, patch, processing, errors } = useForm<CategoryFormData>({
        name: category.name,
        slug: category.slug,
        parent_id: category.parent_id,
        is_active: category.is_active,
    });

    return (
        <>
            <Head title="Edit Category" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    patch(updateCategory(category).url);
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Edit Category</CardTitle>
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
                        Save Changes
                    </Button>
                </div>
            </form>
        </>
    );
}

CategoryEdit.layout = {
    breadcrumbs: [
        { title: 'Categories', href: adminCategoriesRoute.url() },
        { title: 'Edit Category' },
    ],
};
