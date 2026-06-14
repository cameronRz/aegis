import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    destroy as destroyRole,
    edit as editRole,
} from '@/actions/App/Http/Controllers/RoleController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Button } from '@/components/ui/button';
import { roles as rolesRoute } from '@/routes/admin';
import { create as rolesCreateRoute } from '@/routes/admin/roles';
import type { PaginatedData, Role } from '@/types';

type Props = {
    roles: PaginatedData<Role>;
};

type PageErrors = { delete?: string };

const columnHelper = createColumnHelper<Role>();

export default function RolesIndex({ roles }: Props) {
    const { errors } = usePage<{ errors: PageErrors }>().props;
    const [roleToDelete, setRoleToDelete] = useState<Role | null>(null);
    const [deleting, setDeleting] = useState(false);

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', { header: 'Name' }),
            columnHelper.display({
                id: 'description',
                header: 'Description',
                cell: ({ row }) =>
                    row.original.description ?? (
                        <span className="text-muted-foreground">—</span>
                    ),
            }),
            columnHelper.display({
                id: 'permissions_count',
                header: 'Permissions',
                cell: ({ row }) => row.original.permissions?.length ?? 0,
            }),
            columnHelper.display({
                id: 'users_count',
                header: 'Users',
                cell: ({ row }) => row.original.users_count ?? 0,
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            onClick={(e) => e.stopPropagation()}
                        >
                            <Link href={editRole(row.original).url}>Edit</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                setRoleToDelete(row.original);
                            }}
                        >
                            Delete
                        </Button>
                    </div>
                ),
            }),
        ],
        [],
    );

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: roles.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function handleDelete() {
        if (!roleToDelete) return;

        setDeleting(true);

        router.delete(destroyRole(roleToDelete).url, {
            preserveState: true,
            onSuccess: (page) => {
                if (!(page.props as { errors?: PageErrors }).errors?.delete) {
                    setRoleToDelete(null);
                }
                setDeleting(false);
            },
            onError: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title="Roles" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Button asChild>
                        <Link href={rolesCreateRoute.url()}>Create Role</Link>
                    </Button>
                </div>

                <DataTable table={table} emptyMessage="No roles found." />

                <DataTablePagination paginatedData={roles} />
            </div>

            <ConfirmDialog
                open={roleToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setRoleToDelete(null);
                }}
                title="Delete Role"
                description={
                    errors.delete ? (
                        <span className="text-destructive text-sm">{errors.delete}</span>
                    ) : (
                        <>
                            <strong>{roleToDelete?.name}</strong> will be permanently deleted.
                        </>
                    )
                }
                confirmLabel="Delete"
                processing={deleting}
                onConfirm={handleDelete}
            />
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: rolesRoute.url() }],
};
