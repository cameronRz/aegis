import { Head, useForm } from '@inertiajs/react';
import { useRef } from 'react';

import { store as storeCategory } from '@/actions/App/Http/Controllers/CategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { categories as adminCategoriesRoute } from '@/routes/admin';

type ParentCategory = { id: number; name: string };

type Props = {
    parentCategories: ParentCategory[];
};

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/[\s-]+/g, '-');
}

export default function CategoryCreate({ parentCategories }: Props) {
    const slugAutoSync = useRef(true);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        parent_id: null as number | null,
        is_active: true,
    });

    function handleNameChange(value: string) {
        setData('name', value);

        if (slugAutoSync.current) {
            setData('slug', toSlug(value));
        }
    }

    function handleSlugChange(value: string) {
        slugAutoSync.current = false;
        setData('slug', value);
    }

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
                            New categories are automatically positioned after
                            existing ones within their parent. Sort order can be
                            adjusted later.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                value={data.name}
                                onChange={(e) =>
                                    handleNameChange(e.target.value)
                                }
                                placeholder="Category name"
                                autoComplete="off"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                name="slug"
                                value={data.slug}
                                onChange={(e) =>
                                    handleSlugChange(e.target.value)
                                }
                                placeholder="category-slug"
                                autoComplete="off"
                                required
                            />
                            <InputError message={errors.slug} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="parent_id">Parent Category</Label>
                            <Select
                                value={data.parent_id?.toString() ?? 'none'}
                                onValueChange={(value) =>
                                    setData(
                                        'parent_id',
                                        value === 'none' ? null : Number(value),
                                    )
                                }
                            >
                                <SelectTrigger id="parent_id" className="w-64">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Select parent category
                                    </SelectItem>
                                    {parentCategories.map((cat) => (
                                        <SelectItem
                                            key={cat.id}
                                            value={cat.id.toString()}
                                        >
                                            {cat.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.parent_id} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_active"
                                checked={data.is_active}
                                onCheckedChange={(checked) =>
                                    setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="is_active">Active</Label>
                        </div>
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
