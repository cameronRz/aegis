import { Head, useForm } from '@inertiajs/react';
import { store as storePermissionSet } from '@/actions/App/Http/Controllers/PermissionSetController';
import { Button } from '@/components/ui/button';
import { permissionSets as permissionSetsRoute } from '@/routes/admin';
import type { Permission } from '@/types';
import { PermissionSetFormFields } from './permission-set-form-fields';
import type { PermissionSetFormData } from './permission-set-form-fields';

type Props = {
    allPermissions: Permission[];
};

export default function PermissionSetsCreate({ allPermissions }: Props) {
    const { data, setData, post, processing, errors } = useForm<PermissionSetFormData>({
        name: '',
        description: '',
        permissions: [],
    });

    return (
        <>
            <Head title="Create Permission Set" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(storePermissionSet.url());
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
                        Create Permission Set
                    </Button>
                </div>
            </form>
        </>
    );
}

PermissionSetsCreate.layout = {
    breadcrumbs: [
        { title: 'Permission Sets', href: permissionSetsRoute.url() },
        { title: 'Create Permission Set' },
    ],
};
