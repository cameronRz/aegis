import { Head, useForm } from '@inertiajs/react';
import { store as storeUser } from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import { users as adminUsersRoute } from '@/routes/admin';
import type { PermissionSet, Role } from '@/types';
import { UserFormFields } from './user-form-fields';
import type { UserFormData } from './user-form-fields';

type Props = {
    availableRoles: Role[];
    permissionSets: PermissionSet[];
};

export default function UserCreate({ availableRoles, permissionSets }: Props) {
    const { data, setData, post, processing, errors } = useForm<UserFormData>({
        first_name: '',
        last_name: '',
        email: '',
        role: availableRoles[0] ?? 'user',
        permission_set_id: null,
    });

    return (
        <>
            <Head title="Create User" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(storeUser.url());
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <UserFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    availableRoles={availableRoles}
                    permissionSets={permissionSets}
                />

                <div className="flex items-center gap-4">
                    <Button type="submit" disabled={processing}>
                        Create User
                    </Button>
                </div>
            </form>
        </>
    );
}

UserCreate.layout = {
    breadcrumbs: [
        { title: 'Users', href: adminUsersRoute.url() },
        { title: 'Create User' },
    ],
};
