import { Head, router } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    forceDestroy as forceDestroyProduct,
    restore as restoreProduct,
} from '@/actions/App/Http/Controllers/ProductController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { ProductTypeBadge } from '@/components/product-type-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { formatCents } from '@/lib/money';
import { products as adminProductsRoute } from '@/routes/admin';
import { trash as productsTrashRoute } from '@/routes/admin/products';
import type { PaginatedData, Product } from '@/types';

type Props = {
    products: PaginatedData<Product>;
    filters: { search?: string };
};

function formatDeletedAt(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const columnHelper = createColumnHelper<Product>();

type TableProps = {
    products: PaginatedData<Product>;
    onDelete: (product: Product) => void;
};

// Extracted so TanStack Table v8 remounts cleanly via key change (React 19 prop-update incompatibility).
function ProductsTrashTable({ products, onDelete }: TableProps) {
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
        data: products.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <DataTable table={table} emptyMessage="No deleted products." />
            <DataTablePagination paginatedData={products} />
        </>
    );
}

export default function ProductsTrash({ products, filters }: Props) {
    const [search, setSearch] = useDebouncedSearch(filters.search, productsTrashRoute.url());
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState(false);

    /**
     * TanStack Table v8 doesn't re-render when `data` changes via React 19 prop updates.
     * Changing the key forces ProductsTrashTable to remount with the new data.
     */
    const tableKey = products.data.map((p) => `${p.id}:${p.updated_at}`).join(',');

    function handleForceDelete() {
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

                <ProductsTrashTable
                    key={tableKey}
                    products={products}
                    onDelete={setProductToDelete}
                />
            </div>

            <ConfirmDialog
                open={productToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setProductToDelete(null);
                }}
                title="Permanently Delete Product"
                alertTitle="This cannot be undone."
                description={
                    <>
                        <strong>{productToDelete?.name}</strong> will be permanently deleted
                        and cannot be recovered.
                    </>
                }
                confirmLabel="Delete permanently"
                processing={deleting}
                onConfirm={handleForceDelete}
            />
        </>
    );
}

ProductsTrash.layout = {
    breadcrumbs: [
        { title: 'Products', href: adminProductsRoute.url() },
        { title: 'Trash' },
    ],
};
