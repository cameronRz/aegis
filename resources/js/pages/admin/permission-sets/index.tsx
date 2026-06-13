import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    destroy as destroyPermissionSet,
    edit as editPermissionSet,
} from '@/actions/App/Http/Controllers/PermissionSetController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Button } from '@/components/ui/button';
import { permissionSets as permissionSetsRoute } from '@/routes/admin';
import { create as permissionSetsCreateRoute } from '@/routes/admin/permission-sets';
import type { PaginatedData, PermissionSet } from '@/types';

type Props = {
    sets: PaginatedData<PermissionSet>;
};

type PageErrors = { delete?: string };

const columnHelper = createColumnHelper<PermissionSet>();

export default function PermissionSetsIndex({ sets }: Props) {
    const { errors } = usePage<{ errors: PageErrors }>().props;
    const [setToDelete, setSetToDelete] = useState<PermissionSet | null>(null);
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
                cell: ({ row }) => row.original.user_permission_sets_count ?? 0,
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
                            <Link href={editPermissionSet(row.original).url}>Edit</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                setSetToDelete(row.original);
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
        data: sets.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function handleDelete() {
        if (!setToDelete) return;

        setDeleting(true);

        router.delete(destroyPermissionSet(setToDelete).url, {
            preserveState: true,
            onSuccess: (page) => {
                if (!(page.props as { errors?: PageErrors }).errors?.delete) {
                    setSetToDelete(null);
                }
                setDeleting(false);
            },
            onError: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title="Permission Sets" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Button asChild>
                        <Link href={permissionSetsCreateRoute.url()}>Create Permission Set</Link>
                    </Button>
                </div>

                <DataTable table={table} emptyMessage="No permission sets found." />

                <DataTablePagination paginatedData={sets} />
            </div>

            <ConfirmDialog
                open={setToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setSetToDelete(null);
                }}
                title="Delete Permission Set"
                description={
                    errors.delete ? (
                        <span className="text-destructive text-sm">{errors.delete}</span>
                    ) : (
                        <>
                            <strong>{setToDelete?.name}</strong> will be permanently deleted.
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

PermissionSetsIndex.layout = {
    breadcrumbs: [{ title: 'Permission Sets', href: permissionSetsRoute.url() }],
};
