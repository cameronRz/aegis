import { Head, useForm } from '@inertiajs/react';
import { store as storeUser } from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import { users as adminUsersRoute } from '@/routes/admin';
import type { Role, Tier } from '@/types';
import { UserFormFields } from './user-form-fields';
import type { UserFormData } from './user-form-fields';

type Props = {
    availableTiers: Tier[];
    roles: Role[];
};

export default function UserCreate({ availableTiers, roles }: Props) {
    const { data, setData, post, processing, errors } = useForm<UserFormData>({
        first_name: '',
        last_name: '',
        email: '',
        tier: availableTiers[0] ?? 'user',
        role_ids: [],
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
                    availableTiers={availableTiers}
                    roles={roles}
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
