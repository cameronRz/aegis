import { Head, useForm } from '@inertiajs/react';
import { update as updatePermissionSet } from '@/actions/App/Http/Controllers/PermissionSetController';
import { Button } from '@/components/ui/button';
import { permissionSets as permissionSetsRoute } from '@/routes/admin';
import type { Permission, PermissionSet } from '@/types';
import { PermissionSetFormFields } from './permission-set-form-fields';
import type { PermissionSetFormData } from './permission-set-form-fields';

type Props = {
    set: PermissionSet & { permissions: Permission[] };
    allPermissions: Permission[];
};

export default function PermissionSetsEdit({ set, allPermissions }: Props) {
    const { data, setData, patch, processing, errors } = useForm<PermissionSetFormData>({
        name: set.name,
        description: set.description ?? '',
        permissions: set.permissions.map((p) => p.id),
    });

    return (
        <>
            <Head title={`Edit ${set.name}`} />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    patch(updatePermissionSet(set).url);
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <PermissionSetFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    allPermissions={allPermissions}
                />

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        Save Changes
                    </Button>
                </div>
            </form>
        </>
    );
}

PermissionSetsEdit.layout = {
    breadcrumbs: [
        { title: 'Permission Sets', href: permissionSetsRoute.url() },
        { title: 'Edit Permission Set' },
    ],
};
