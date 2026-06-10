import { Head, router } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useMemo, useState } from 'react';

import {
    forceDestroy as forceDestroyProduct,
    restore as restoreProduct,
} from '@/actions/App/Http/Controllers/ProductController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { trash as productsTrashRoute } from '@/routes/admin/products';
import { products as adminProductsRoute } from '@/routes/admin';
import type { PaginatedData, Product, ProductType } from '@/types';

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

function formatDeletedAt(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const columnHelper = createColumnHelper<Product>();

export default function ProductsTrash({ products, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState(false);

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', { header: 'Name' }),
            columnHelper.accessor('sku', { header: 'SKU' }),
            columnHelper.accessor('type', {
                header: 'Type',
                cell: ({ getValue }) => {
                    const { label, variant } = typeConfig[getValue()];

                    return <Badge variant={variant}>{label}</Badge>;
                },
            }),
            columnHelper.accessor('price', {
                header: 'Price',
                cell: ({ getValue }) => formatCents(getValue()),
            }),
            columnHelper.display({
                id: 'deleted_at',
                header: 'Deleted',
                cell: ({ row }) =>
                    row.original.deleted_at ? formatDeletedAt(row.original.deleted_at) : '—',
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
                                router.post(restoreProduct(row.original).url, {}, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            Restore
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                setProductToDelete(row.original);
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

    useEffect(() => {
        // eslint-disable-next-line curly
        if (search === (filters.search ?? '')) return;

        const timer = setTimeout(() => {
            router.get(
                productsTrashRoute.url(),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search, filters.search]);

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

    function handleForceDelete() {
        // eslint-disable-next-line curly
        if (!productToDelete) return;

        setDeleting(true);

        router.delete(forceDestroyProduct(productToDelete).url, {
            onSuccess: () => {
                setProductToDelete(null);
                setDeleting(false);
            },
            onError: () => setDeleting(false),
        });
    }

    const pageLinks = products.links.filter(
        (link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    );

    return (
        <>
            <Head title="Product Trash" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
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
                                    <TableRow key={row.id}>
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
                                        No deleted products.
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

            <Dialog
                open={productToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setProductToDelete(null);
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <DialogTitle>Permanently Delete Product</DialogTitle>
                    <Alert variant="destructive">
                        <AlertTitle>This cannot be undone.</AlertTitle>
                        <AlertDescription>
                            <strong>{productToDelete?.name}</strong> will be permanently deleted
                            and cannot be recovered.
                        </AlertDescription>
                    </Alert>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={deleting}
                            onClick={handleForceDelete}
                        >
                            Delete permanently
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductsTrash.layout = {
    breadcrumbs: [
        { title: 'Products', href: adminProductsRoute.url() },
        { title: 'Trash' },
    ],
};
