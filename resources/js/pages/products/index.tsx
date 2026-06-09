import { Head, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useMemo, useRef, useState } from 'react';

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
import { formatCents } from '@/lib/money';
import { products as adminProductsRoute } from '@/routes/admin';
import type { Auth, PaginatedData, Product, ProductType } from '@/types';

type Props = {
    products: PaginatedData<Product>;
    filters: { search?: string };
};

const typeConfig: Record<
    ProductType,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    physical: { label: 'Physical', variant: 'default' },
    digital: { label: 'Digital', variant: 'secondary' },
    subscription: { label: 'Subscription', variant: 'outline' },
};

function ProductTypeBadge({ type }: { type: ProductType }) {
    const { label, variant } = typeConfig[type];

    return <Badge variant={variant}>{label}</Badge>;
}

const columnHelper = createColumnHelper<Product>();

type PageProps = { auth: Auth };

export default function ProductsIndex({ products, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', { header: 'Name' }),
            columnHelper.accessor('sku', { header: 'SKU' }),
            columnHelper.accessor('type', {
                header: 'Type',
                cell: ({ getValue }) => <ProductTypeBadge type={getValue()} />,
            }),
            columnHelper.accessor('price', {
                header: 'Price',
                cell: ({ getValue }) => formatCents(getValue()),
            }),
            columnHelper.display({
                id: 'category',
                header: 'Category',
                cell: ({ row }) =>
                    row.original.category?.name ?? (
                        <span className="text-muted-foreground">—</span>
                    ),
            }),
            ...(auth.can.edit_product
                ? [
                      columnHelper.display({
                          id: 'actions',
                          header: '',
                          cell: () => (
                              <div className="flex justify-end">
                                  <Button
                                      variant="outline"
                                      size="sm"
                                      disabled
                                      onClick={(e) => e.stopPropagation()}
                                  >
                                      Edit
                                  </Button>
                              </div>
                          ),
                      }),
                  ]
                : []),
        ],
        [auth.can.edit_product],
    );

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            router.get(
                adminProductsRoute.url(),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: products.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function goToPage(url: string | null) {
        // eslint-disable-next-line
        if (!url) return;

        router.get(url, {}, { preserveState: true });
    }

    const pageLinks = products.links.filter(
        (link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    );

    return (
        <>
            <Head title="Products" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Input
                        placeholder="Search by name or SKU..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
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
                                        className={!row.original.is_active ? 'opacity-50' : ''}
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
                                        No products found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {products.from
                            ? `Showing ${products.from}–${products.to} of ${products.total}`
                            : 'No results'}
                    </span>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!products.prev_page_url}
                            onClick={() => goToPage(products.prev_page_url)}
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
                            disabled={!products.next_page_url}
                            onClick={() => goToPage(products.next_page_url)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: adminProductsRoute.url() }],
};
