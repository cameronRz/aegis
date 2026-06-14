import { Head, useForm } from '@inertiajs/react';
import { update as updateUser } from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import { users as adminUsersRoute } from '@/routes/admin';
import type { Role, Tier, User } from '@/types';
import { UserFormFields } from './user-form-fields';
import type { UserFormData } from './user-form-fields';

type Props = {
    user: User;
    availableTiers: Tier[];
    roles: Role[];
    selectedRoleIds: number[];
};

export default function UserEdit({ user, availableTiers, roles, selectedRoleIds }: Props) {
    const { data, setData, patch, processing, errors } = useForm<UserFormData>({
        first_name: user.first_name,
        last_name: user.last_name,
        email: user.email,
        tier: user.tier,
        role_ids: selectedRoleIds,
    });

    return (
        <>
            <Head title={`Edit ${user.full_name}`} />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    patch(updateUser(user).url);
                }}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <UserFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    availableTiers={availableTiers}
                    roles={roles}
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

UserEdit.layout = {
    breadcrumbs: [
        { title: 'Users', href: adminUsersRoute.url() },
        { title: 'Edit User' },
    ],
};
