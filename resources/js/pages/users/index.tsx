import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { users as adminUsersRoute } from '@/routes/admin';
import type { Auth, PaginatedData, Role, User } from '@/types';

function goToUser(user: User) {
    router.visit(adminUsersRoute.show(user.id).url);
}

type Props = {
    users: PaginatedData<User>;
    filters: { search?: string };
};

const roleConfig: Record<
    Role,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    site_admin: { label: 'Site Admin', variant: 'destructive' },
    admin: { label: 'Admin', variant: 'default' },
    manager: { label: 'Manager', variant: 'secondary' },
    user: { label: 'User', variant: 'outline' },
};

function RoleBadge({ role }: { role: Role }) {
    const { label, variant } = roleConfig[role];

    return <Badge variant={variant}>{label}</Badge>;
}

const columnHelper = createColumnHelper<User>();

const columns = [
    columnHelper.accessor('first_name', { header: 'First Name' }),
    columnHelper.accessor('last_name', { header: 'Last Name' }),
    columnHelper.accessor('email', { header: 'Email' }),
    columnHelper.accessor('role', {
        header: 'Role',
        cell: ({ getValue }) => <RoleBadge role={getValue() as Role} />,
    }),
];

type PageProps = { auth: Auth };

export default function UsersIndex({ users, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                adminUsersRoute.url(),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: users.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function goToPage(url: string | null) {
        if (!url) return;

        router.get(url, {}, { preserveState: true });
    }

    const pageLinks = users.links.filter(
        (link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    );

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
                    {auth.can.create_user && (
                        <Button asChild>
                            <Link href={adminUsersRoute.create.url()}>Create User</Link>
                        </Button>
                    )}
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead key={header.id}>
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length ? (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className="cursor-pointer"
                                        onClick={() => goToUser(row.original)}
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="h-24 text-center"
                                    >
                                        No users found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {users.from
                            ? `Showing ${users.from}–${users.to} of ${users.total}`
                            : 'No results'}
                    </span>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!users.prev_page_url}
                            onClick={() => goToPage(users.prev_page_url)}
                        >
                            Previous
                        </Button>
                        {pageLinks.map((link) => (
                            <Button
                                key={link.label}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => goToPage(link.url)}
                            >
                                {link.label}
                            </Button>
                        ))}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!users.next_page_url}
                            onClick={() => goToPage(users.next_page_url)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: adminUsersRoute.url() }],
};
