import { Head, useForm } from '@inertiajs/react';
import type { RoleFormData } from './role-form-fields';
import { RoleFormFields } from './role-form-fields';
import { store as storeRole } from '@/actions/App/Http/Controllers/RoleController';
import { Button } from '@/components/ui/button';
import { roles as rolesRoute } from '@/routes/admin';
import type { Permission } from '@/types';

type Props = {
    allPermissions: Permission[];
};

export default function RolesCreate({ allPermissions }: Props) {
    const { data, setData, post, processing, errors } = useForm<RoleFormData>({
        name: '',
        description: '',
        color: '',
        permissions: [],
    });

    return (
        <>
            <Head title="Create Role" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(storeRole.url());
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
                        Create Role
                    </Button>
                </div>
            </form>
        </>
    );
}

RolesCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: rolesRoute.url() },
        { title: 'Create Role' },
    ],
};
