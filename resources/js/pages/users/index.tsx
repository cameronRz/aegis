import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useMemo, useState } from 'react';
import { create as createUser, edit as editUser, show as showUser } from '@/actions/App/Http/Controllers/UserController';
import { BulkAssignRolesModal } from '@/components/bulk-assign-roles-modal';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { users as adminUsersRoute } from '@/routes/admin';
import { trash as usersTrashRoute } from '@/routes/admin/users';
import { PRIVILEGED_TIERS } from '@/types';
import type { Auth, PaginatedData, Role, Tier, User } from '@/types';

function goToUser(user: User) {
    router.visit(showUser(user).url);
}

type Props = {
    users: PaginatedData<User>;
    filters: { search?: string };
    roles: Role[];
};

const columnHelper = createColumnHelper<User>();

type PageProps = { auth: Auth };

export default function UsersIndex({ users, filters, roles }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useDebouncedSearch(filters.search, adminUsersRoute.url());
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [modalOpen, setModalOpen] = useState(false);

    useEffect(() => {
        setSelectedIds([]);
    }, [users.current_page, filters.search]);

    const canEditRow = (target: User) =>
        auth.can.edit_user &&
        auth.user.id !== target.id &&
        (auth.user.tier === 'site_admin' || !PRIVILEGED_TIERS.includes(target.tier as Tier));

    const columns = useMemo(() => {
        const allIds = users.data.map((u) => u.id);
        const allSelected = allIds.length > 0 && allIds.every((id) => selectedIds.includes(id));
        const someSelected = !allSelected && allIds.some((id) => selectedIds.includes(id));

        return [
            ...(auth.can.edit_user
                ? [
                      columnHelper.display({
                          id: 'select',
                          header: () => (
                              <Checkbox
                                  checked={allSelected ? true : someSelected ? 'indeterminate' : false}
                                  onCheckedChange={(checked) => setSelectedIds(checked ? allIds : [])}
                                  aria-label="Select all"
                                  onClick={(e) => e.stopPropagation()}
                              />
                          ),
                          cell: ({ row }) => (
                              <Checkbox
                                  checked={selectedIds.includes(row.original.id)}
                                  onCheckedChange={(checked) =>
                                      setSelectedIds((prev) =>
                                          checked
                                              ? [...prev, row.original.id]
                                              : prev.filter((id) => id !== row.original.id),
                                      )
                                  }
                                  aria-label={`Select ${row.original.full_name}`}
                                  onClick={(e) => e.stopPropagation()}
                              />
                          ),
                      }),
                  ]
                : []),
            columnHelper.accessor('first_name', { header: 'First Name' }),
            columnHelper.accessor('last_name', { header: 'Last Name' }),
            columnHelper.accessor('email', { header: 'Email' }),
            columnHelper.accessor('roles', {
                header: 'Roles',
                cell: ({ getValue }) => {
                    const roles = getValue();

                    if (!roles?.length) {
                        return <span className="text-muted-foreground">—</span>;
                    }

                    return (
                        <div className="flex flex-wrap gap-1">
                            {roles.map((role) =>
                                role.color ? (
                                    <Badge
                                        key={role.id}
                                        variant="outline"
                                        style={{ borderColor: role.color }}
                                    >
                                        {role.name}
                                    </Badge>
                                ) : (
                                    <Badge key={role.id} variant="secondary">
                                        {role.name}
                                    </Badge>
                                ),
                            )}
                        </div>
                    );
                },
            }),
            ...(auth.can.edit_user
                ? [
                      columnHelper.display({
                          id: 'actions',
                          header: '',
                          cell: ({ row }) =>
                              canEditRow(row.original) ? (
                                  <div className="flex justify-end">
                                      <Button
                                          variant="outline"
                                          size="sm"
                                          asChild
                                          onClick={(e) => e.stopPropagation()}
                                      >
                                          <Link href={editUser(row.original).url}>Edit</Link>
                                      </Button>
                                  </div>
                              ) : null,
                      }),
                  ]
                : []),
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [users.data, selectedIds, auth.can.edit_user, auth.user.id, auth.user.tier]);

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: users.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Input
                        placeholder="Search by name or email..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                    <div className="flex items-center gap-3">
                        {auth.can.admin && (
                            <Link
                                href={usersTrashRoute.url()}
                                className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                            >
                                View Trash
                            </Link>
                        )}
                        {auth.can.create_user && (
                            <Button asChild>
                                <Link href={createUser.url()}>Create User</Link>
                            </Button>
                        )}
                    </div>
                </div>

                {auth.can.edit_user && selectedIds.length > 0 && (
                    <div className="flex items-center gap-3 rounded-md border bg-muted/50 px-4 py-2">
                        <span className="text-sm text-muted-foreground">
                            {selectedIds.length} user{selectedIds.length !== 1 ? 's' : ''} selected
                        </span>
                        <Button size="sm" variant="outline" onClick={() => setModalOpen(true)}>
                            Assign Role
                        </Button>
                        <button
                            onClick={() => setSelectedIds([])}
                            className="ml-auto text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Clear
                        </button>
                    </div>
                )}

                <DataTable
                    table={table}
                    emptyMessage="No users found."
                    onRowClick={(row) => goToUser(row.original)}
                />

                <DataTablePagination paginatedData={users} />
            </div>

            <BulkAssignRolesModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                roles={roles}
                selectedUserIds={selectedIds}
                onSuccess={() => setSelectedIds([])}
            />
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: adminUsersRoute.url() }],
};
