import { flexRender } from '@tanstack/react-table';
import type { Row, Table } from '@tanstack/react-table';
import {
    Table as ShadTable,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Props<TData> = {
    table: Table<TData>;
    emptyMessage?: string;
    onRowClick?: (row: Row<TData>) => void;
    getRowClassName?: (row: Row<TData>) => string;
};

export function DataTable<TData>({
    table,
    emptyMessage = 'No results.',
    onRowClick,
    getRowClassName,
}: Props<TData>) {
    const rows = table.getRowModel().rows;

    return (
        <div className="rounded-md border">
            <ShadTable>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead key={header.id}>
                                    {flexRender(header.column.columnDef.header, header.getContext())}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {rows.length ? (
                        rows.map((row) => (
                            <TableRow
                                key={row.id}
                                className={getRowClassName?.(row)}
                                onClick={onRowClick ? () => onRowClick(row) : undefined}
                                style={onRowClick ? { cursor: 'pointer' } : undefined}
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={table.getAllColumns().length}
                                className="h-24 text-center"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </ShadTable>
        </div>
    );
}
