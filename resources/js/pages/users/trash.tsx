import { Head, router } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    forceDestroy as forceDestroyUser,
    restore as restoreUser,
} from '@/actions/App/Http/Controllers/UserController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { TierBadge } from '@/components/tier-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { users as adminUsersRoute } from '@/routes/admin';
import { trash as usersTrashRoute } from '@/routes/admin/users';
import type { PaginatedData, Tier, User } from '@/types';

type Props = {
    users: PaginatedData<User>;
    filters: { search?: string };
};

function formatDeletedAt(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const columnHelper = createColumnHelper<User>();

type TableProps = {
    users: PaginatedData<User>;
    onDelete: (user: User) => void;
};

// Extracted so TanStack Table v8 remounts cleanly via key change (React 19 prop-update incompatibility).
function UsersTrashTable({ users, onDelete }: TableProps) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('first_name', { header: 'First Name' }),
            columnHelper.accessor('last_name', { header: 'Last Name' }),
            columnHelper.accessor('email', { header: 'Email' }),
            columnHelper.accessor('tier', {
                header: 'Tier',
                cell: ({ getValue }) => <TierBadge tier={getValue() as Tier} />,
            }),
            columnHelper.display({
                id: 'deleted_at',
                header: 'Deleted',
                cell: ({ row }) =>
                    row.original.deleted_at
                        ? formatDeletedAt(row.original.deleted_at)
                        : '—',
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                router.post(
                                    restoreUser(row.original).url,
                                    {},
                                    {
                                        preserveScroll: true,
                                    },
                                );
                            }}
                        >
                            Restore
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                onDelete(row.original);
                            }}
                        >
                            Delete
                        </Button>
                    </div>
                ),
            }),
        ],
        [onDelete],
    );

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: users.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <DataTable table={table} emptyMessage="No deleted users." />
            <DataTablePagination paginatedData={users} />
        </>
    );
}

export default function UsersTrash({ users, filters }: Props) {
    const [search, setSearch] = useDebouncedSearch(
        filters.search,
        usersTrashRoute.url(),
    );
    const [userToDelete, setUserToDelete] = useState<User | null>(null);
    const [deleting, setDeleting] = useState(false);

    /**
     * TanStack Table v8 doesn't re-render when `data` changes via React 19 prop updates.
     * Changing the key forces UsersTrashTable to remount with the new data.
     */
    const tableKey = users.data.map((u) => `${u.id}:${u.updated_at}`).join(',');

    function handleForceDelete() {
        if (!userToDelete) return;

        setDeleting(true);

        router.delete(forceDestroyUser(userToDelete).url, {
            onSuccess: () => {
                setUserToDelete(null);
                setDeleting(false);
            },
            onError: () => setDeleting(false),
        });
    }

    return (
        <>
            <Head title="User Trash" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Input
                        placeholder="Search by name or email..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                </div>

                <UsersTrashTable
                    key={tableKey}
                    users={users}
                    onDelete={setUserToDelete}
                />
            </div>

            <ConfirmDialog
                open={userToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setUserToDelete(null);
                }}
                title="Permanently Delete User"
                alertTitle="This cannot be undone."
                description={
                    <>
                        <strong>{userToDelete?.full_name}</strong> will be
                        permanently deleted and cannot be recovered.
                    </>
                }
                confirmLabel="Delete permanently"
                processing={deleting}
                onConfirm={handleForceDelete}
            />
        </>
    );
}

UsersTrash.layout = {
    breadcrumbs: [
        { title: 'Users', href: adminUsersRoute.url() },
        { title: 'Trash' },
    ],
};
