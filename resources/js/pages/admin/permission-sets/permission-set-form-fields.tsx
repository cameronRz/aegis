import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { Permission } from '@/types';

export type PermissionSetFormData = {
    name: string;
    description: string;
    permissions: number[];
};

type Props = {
    data: PermissionSetFormData;
    setData: <K extends keyof PermissionSetFormData>(key: K, value: PermissionSetFormData[K]) => void;
    errors: Partial<Record<keyof PermissionSetFormData, string>>;
    allPermissions: Permission[];
};

function getGroupLabel(name: string): string {
    const noun = name.split('_').slice(1).join('_');
    const labels: Record<string, string> = {
        user: 'Users', users: 'Users',
        category: 'Categories', categories: 'Categories',
        product: 'Products', products: 'Products',
    };
    return labels[noun] ?? noun.replace(/_/g, ' ');
}

function toggleId(ids: number[], id: number): number[] {
    return ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id];
}

export function PermissionSetFormFields({ data, setData, errors, allPermissions }: Props) {
    const grouped = useMemo(() => {
        const groups: Record<string, Permission[]> = {};
        for (const p of allPermissions) {
            const label = getGroupLabel(p.name);
            (groups[label] ??= []).push(p);
        }
        return groups;
    }, [allPermissions]);

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="e.g. Support Staff"
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>
                        <Input
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Optional description"
                            autoComplete="off"
                        />
                        <InputError message={errors.description} />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Permissions</CardTitle>
                </CardHeader>
                <CardContent>
                    {Object.entries(grouped).map(([group, permissions], groupIndex) => (
                        <div key={group}>
                            {groupIndex > 0 && <Separator className="my-4" />}
                            <p className="mb-3 text-sm font-medium">{group}</p>
                            <div className="space-y-2">
                                {permissions.map((permission) => (
                                    <div key={permission.id} className="flex items-center gap-3">
                                        <Checkbox
                                            id={`perm-${permission.id}`}
                                            checked={data.permissions.includes(permission.id)}
                                            onCheckedChange={() =>
                                                setData('permissions', toggleId(data.permissions, permission.id))
                                            }
                                        />
                                        <Label
                                            htmlFor={`perm-${permission.id}`}
                                            className="cursor-pointer font-normal"
                                        >
                                            {permission.display_name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                    {allPermissions.length === 0 && (
                        <p className="text-muted-foreground text-sm">No permissions defined.</p>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
