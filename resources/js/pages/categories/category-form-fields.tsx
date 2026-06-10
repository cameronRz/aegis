import { useRef } from 'react';

import InputError from '@/components/input-error';
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

export type CategoryFormData = {
    name: string;
    slug: string;
    parent_id: number | null;
    is_active: boolean;
};

export type ParentCategory = { id: number; name: string };

type Props = {
    data: CategoryFormData;
    setData: <K extends keyof CategoryFormData>(key: K, value: CategoryFormData[K]) => void;
    errors: Partial<Record<keyof CategoryFormData, string>>;
    parentCategories: ParentCategory[];
};

function toSlug(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/[\s-]+/g, '-');
}

export function CategoryFormFields({ data, setData, errors, parentCategories }: Props) {
    const slugAutoSync = useRef(data.slug === '');

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
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    value={data.name}
                    onChange={(e) => handleNameChange(e.target.value)}
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
                    onChange={(e) => handleSlugChange(e.target.value)}
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
                        setData('parent_id', value === 'none' ? null : Number(value))
                    }
                >
                    <SelectTrigger id="parent_id" className="w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">None (root category)</SelectItem>
                        {parentCategories.map((cat) => (
                            <SelectItem key={cat.id} value={cat.id.toString()}>
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
                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                />
                <Label htmlFor="is_active">Active</Label>
            </div>
        </>
    );
}
