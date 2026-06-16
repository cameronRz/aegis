import { Head, router } from '@inertiajs/react';
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useMemo } from 'react';
import { show as showOrder } from '@/actions/App/Http/Controllers/OrderController';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Badge } from '@/components/ui/badge';
import { orderStatusConfig } from '@/lib/order-status';
import { formatCents } from '@/lib/money';
import { orders as ordersRoute } from '@/routes';
import type { Order, PaginatedData } from '@/types';

type OrderWithCount = Order & { items_count: number };

type Props = {
    orders: PaginatedData<OrderWithCount>;
};

const columnHelper = createColumnHelper<OrderWithCount>();

const columns = [
    columnHelper.accessor('order_number', {
        header: 'Order',
        cell: ({ getValue }) => <span className="font-mono text-sm">{getValue()}</span>,
    }),
    columnHelper.accessor('created_at', {
        header: 'Date',
        cell: ({ getValue }) => new Date(getValue()).toLocaleDateString(),
    }),
    columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ getValue }) => {
            const { label, variant } = orderStatusConfig[getValue()];

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

export default function OrdersIndex({ orders }: Props) {
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
                <DataTable
                    table={table}
                    emptyMessage="No orders yet."
                    onRowClick={(row) => router.visit(showOrder(row.original).url)}
                />
                <DataTablePagination paginatedData={orders} />
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: ordersRoute.url() }],
};
