import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    create as createProduct,
    destroy as destroyProduct,
    edit as editProduct,
    show as showProduct,
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
import { PRIVILEGED_ROLES } from '@/types';
import type { Auth, PaginatedData, Product, Role } from '@/types';

type Props = {
    products: PaginatedData<Product>;
    filters: { search?: string };
};

const columnHelper = createColumnHelper<Product>();

type PageProps = { auth: Auth };

function goToProduct(product: Product) {
    router.visit(showProduct(product).url);
}

export default function ProductsIndex({ products, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useDebouncedSearch(filters.search, adminProductsRoute.url());
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState(false);

    const canEdit = auth.can.edit_product;
    const canDelete = auth.can.delete_product;

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
            ...(canEdit || canDelete
                ? [
                      columnHelper.display({
                          id: 'actions',
                          header: '',
                          cell: ({ row }) => (
                              <div className="flex justify-end gap-2">
                                  {canEdit && (
                                      <Button
                                          variant="outline"
                                          size="sm"
                                          asChild
                                          onClick={(e) => e.stopPropagation()}
                                      >
                                          <Link href={editProduct(row.original).url}>Edit</Link>
                                      </Button>
                                  )}
                                  {canDelete && (
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
                                  )}
                              </div>
                          ),
                      }),
                  ]
                : []),
        ],
        [canEdit, canDelete],
    );

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: products.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function handleDelete() {
        if (!productToDelete) return;

        setDeleting(true);

        router.delete(destroyProduct(productToDelete).url, {
            onSuccess: () => {
                setProductToDelete(null);
                setDeleting(false);
            },
            onError: () => setDeleting(false),
        });
    }

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
                    <div className="flex items-center gap-3">
                        {PRIVILEGED_ROLES.includes(auth.user.role as Role) && (
                            <Link
                                href={productsTrashRoute.url()}
                                className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                            >
                                View Trash
                            </Link>
                        )}
                        {auth.can.create_product && (
                            <Button asChild>
                                <Link href={createProduct.url()}>Create Product</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <DataTable
                    table={table}
                    emptyMessage="No products found."
                    onRowClick={(row) => goToProduct(row.original)}
                    getRowClassName={(row) => (row.original.is_active ? '' : 'opacity-50')}
                />

                <DataTablePagination paginatedData={products} />
            </div>

            <ConfirmDialog
                open={productToDelete !== null}
                onOpenChange={(open) => { if (!open) setProductToDelete(null); }}
                title="Delete Product"
                description={
                    <>
                        <strong>{productToDelete?.name}</strong> will be moved to the trash.
                    </>
                }
                processing={deleting}
                onConfirm={handleDelete}
            />
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: adminProductsRoute.url() }],
};
