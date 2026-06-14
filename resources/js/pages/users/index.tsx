import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import { create as createUser, edit as editUser, show as showUser } from '@/actions/App/Http/Controllers/UserController';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { TierBadge } from '@/components/tier-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { users as adminUsersRoute } from '@/routes/admin';
import { trash as usersTrashRoute } from '@/routes/admin/users';
import { PRIVILEGED_TIERS } from '@/types';
import type { Auth, PaginatedData, Tier, User } from '@/types';

function goToUser(user: User) {
    router.visit(showUser(user).url);
}

type Props = {
    users: PaginatedData<User>;
    filters: { search?: string };
};

const columnHelper = createColumnHelper<User>();

type PageProps = { auth: Auth };

export default function UsersIndex({ users, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useDebouncedSearch(filters.search, adminUsersRoute.url());

    const canEditRow = (target: User) =>
        auth.can.edit_user &&
        auth.user.id !== target.id &&
        (auth.user.tier === 'site_admin' || !PRIVILEGED_TIERS.includes(target.tier as Tier));

    const columns = useMemo(
        () => [
            columnHelper.accessor('first_name', { header: 'First Name' }),
            columnHelper.accessor('last_name', { header: 'Last Name' }),
            columnHelper.accessor('email', { header: 'Email' }),
            columnHelper.accessor('tier', {
                header: 'Tier',
                cell: ({ getValue }) => <TierBadge tier={getValue() as Tier} />,
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
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [auth.can.edit_user, auth.user.id, auth.user.tier],
    );

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

                <DataTable
                    table={table}
                    emptyMessage="No users found."
                    onRowClick={(row) => goToUser(row.original)}
                />

                <DataTablePagination paginatedData={users} />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: adminUsersRoute.url() }],
};
