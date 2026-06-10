import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useMemo, useState } from 'react';

import {
    create as createProduct,
    destroy as destroyProduct,
    edit as editProduct,
    show as showProduct,
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
import { PRIVILEGED_ROLES } from '@/types';
import type { Auth, PaginatedData, Product, ProductType, Role } from '@/types';

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

function goToProduct(product: Product) {
    router.visit(showProduct(product).url);
}

export default function ProductsIndex({ products, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
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

    useEffect(() => {
        if (search === (filters.search ?? '')) return;

        const timer = setTimeout(() => {
            router.get(
                adminProductsRoute.url(),
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
                                        className={`cursor-pointer${!row.original.is_active ? ' opacity-50' : ''}`}
                                        onClick={() => goToProduct(row.original)}
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
            <Dialog
                open={productToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setProductToDelete(null);
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <DialogTitle>Delete Product</DialogTitle>
                    <Alert variant="destructive">
                        <AlertTitle>Are you sure?</AlertTitle>
                        <AlertDescription>
                            <strong>{productToDelete?.name}</strong> will be moved to the trash.
                        </AlertDescription>
                    </Alert>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button variant="destructive" disabled={deleting} onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: adminProductsRoute.url() }],
};
