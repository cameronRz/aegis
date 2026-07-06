import { Head, router } from '@inertiajs/react';
import { createColumnHelper, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useEffect, useMemo } from 'react';
import { ClientDate } from '@/components/client-date';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Badge } from '@/components/ui/badge';
import { index as adminSupportIndex, show as showAdminConversation } from '@/routes/admin/support';
import type { PaginatedData, SupportConversation } from '@/types';

type ConversationRow = SupportConversation & { unread_count: number };

type Props = {
    conversations: PaginatedData<ConversationRow>;
};

const columnHelper = createColumnHelper<ConversationRow>();

const columns = [
    columnHelper.accessor('client', {
        header: 'Client',
        cell: ({ getValue }) => {
            const client = getValue();

            return client ? (
                <span className="font-medium">{client.full_name}</span>
            ) : (
                <span className="text-muted-foreground">—</span>
            );
        },
    }),
    columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ getValue }) => {
            const status = getValue();

            return (
                <Badge variant={status === 'open' ? 'default' : 'outline'}>
                    {status === 'open' ? 'Open' : 'Closed'}
                </Badge>
            );
        },
    }),
    columnHelper.accessor('last_message_at', {
        header: 'Last Message',
        cell: ({ getValue }) => {
            const val = getValue();

            return val ? <ClientDate iso={val} options={{ dateStyle: 'short', timeStyle: 'short' }} /> : '—';
        },
    }),
    columnHelper.accessor('unread_count', {
        header: 'Unread',
        cell: ({ getValue }) => {
            const count = getValue();

            return count > 0 ? (
                <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-medium text-primary-foreground tabular-nums">
                    {count}
                </span>
            ) : null;
        },
    }),
];

export default function AdminSupportIndex({ conversations }: Props) {
    // Inertia restores the history-cached page state on back navigation without re-fetching,
    // so counts can be stale if the admin read a conversation then pressed back. Reload on
    // mount to always show current counts.
    useEffect(() => {
        router.reload({ only: ['conversations', 'unreadSupportCount'] });
    }, []);

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: conversations.data,
        columns: useMemo(() => columns, []),
        getCoreRowModel: getCoreRowModel(),
        // Include unread_count in the row ID so React remounts cells when the count changes.
        // TanStack Table v8 doesn't re-render cells on data prop updates under React 19.
        getRowId: (row) => `${row.id}-${row.unread_count}`,
    });

    return (
        <>
            <Head title="Support" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <DataTable
                    table={table}
                    emptyMessage="No conversations yet."
                    onRowClick={(row) => router.visit(showAdminConversation(row.original).url)}
                />
                <DataTablePagination paginatedData={conversations} />
            </div>
        </>
    );
}

AdminSupportIndex.layout = {
    breadcrumbs: [{ title: 'Support', href: adminSupportIndex.url() }],
};
