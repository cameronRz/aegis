import { Head, router } from '@inertiajs/react';
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useMemo } from 'react';
import { show as showAdminOrder } from '@/actions/App/Http/Controllers/Admin/OrderController';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { formatCents } from '@/lib/money';
import { orders as adminOrdersRoute } from '@/routes/admin';
import type { Order, OrderStatus, PaginatedData, User } from '@/types';

type OrderRow = Order & { items_count: number; user: User | null };

type Props = {
    orders: PaginatedData<OrderRow>;
    filters: { search?: string };
};

const statusConfig: Record<OrderStatus, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    pending: { label: 'Processing', variant: 'secondary' },
    paid: { label: 'Paid', variant: 'default' },
    failed: { label: 'Failed', variant: 'destructive' },
    refunded: { label: 'Refunded', variant: 'outline' },
    expired: { label: 'Expired', variant: 'outline' },
};

const columnHelper = createColumnHelper<OrderRow>();

const columns = [
    columnHelper.accessor('order_number', {
        header: 'Order',
        cell: ({ getValue }) => <span className="font-mono text-sm">{getValue()}</span>,
    }),
    columnHelper.accessor('user', {
        header: 'Client',
        cell: ({ getValue }) => {
            const user = getValue();

            return user ? (
                <div className="flex flex-col">
                    <span className="font-medium">{user.full_name}</span>
                    <span className="text-muted-foreground text-xs">{user.email}</span>
                </div>
            ) : (
                <span className="text-muted-foreground text-sm">—</span>
            );
        },
    }),
    columnHelper.accessor('created_at', {
        header: 'Date',
        cell: ({ getValue }) => new Date(getValue()).toLocaleDateString(),
    }),
    columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ getValue }) => {
            const { label, variant } = statusConfig[getValue()];

            return <Badge variant={variant}>{label}</Badge>;
        },
    }),
    columnHelper.accessor('items_count', {
        header: 'Items',
        cell: ({ getValue }) => getValue(),
    }),
    columnHelper.accessor('total', {
        header: 'Total',
        cell: ({ getValue }) => <span className="tabular-nums">{formatCents(getValue())}</span>,
    }),
];

export default function AdminOrdersIndex({ orders, filters }: Props) {
    const [search, setSearch] = useDebouncedSearch(filters.search, adminOrdersRoute.url());

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: orders.data,
        columns: useMemo(() => columns, []),
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Orders" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Input
                        placeholder="Search by order number, name or email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                </div>

                <DataTable
                    table={table}
                    emptyMessage="No orders found."
                    onRowClick={(row) => router.visit(showAdminOrder(row.original).url)}
                />

                <DataTablePagination paginatedData={orders} />
            </div>
        </>
    );
}

AdminOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: adminOrdersRoute.url() }],
};
