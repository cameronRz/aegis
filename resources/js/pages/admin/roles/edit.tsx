import { Head, useForm } from '@inertiajs/react';
import { update as updateRole } from '@/actions/App/Http/Controllers/RoleController';
import { Button } from '@/components/ui/button';
import { roles as rolesRoute } from '@/routes/admin';
import type { Permission, Role } from '@/types';
import { RoleFormFields } from './role-form-fields';
import type { RoleFormData } from './role-form-fields';

type Props = {
    role: Role & { permissions: Permission[] };
    allPermissions: Permission[];
};

export default function RolesEdit({ role, allPermissions }: Props) {
    const { data, setData, patch, processing, errors } = useForm<RoleFormData>({
        name: role.name,
        description: role.description ?? '',
        permissions: role.permissions.map((p) => p.id),
    });

    return (
        <>
            <Head title={`Edit ${role.name}`} />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    patch(updateRole(role).url);
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <RoleFormFields
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

RolesEdit.layout = {
    breadcrumbs: [
        { title: 'Roles', href: rolesRoute.url() },
        { title: 'Edit Role' },
    ],
};
