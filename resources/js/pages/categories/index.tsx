import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useEffect, useMemo, useState } from 'react';

import {
    destroy as destroyCategory,
    edit as editCategory,
} from '@/actions/App/Http/Controllers/CategoryController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { create as categoriesCreateRoute } from '@/routes/admin/categories';
import { categories as adminCategoriesRoute } from '@/routes/admin';
import type { Auth, Category, PaginatedData } from '@/types';

type Props = {
    categories: PaginatedData<Category>;
    filters: { search?: string };
};

const columnHelper = createColumnHelper<Category>();

type PageProps = { auth: Auth };

export default function CategoriesIndex({ categories, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryToDelete, setCategoryToDelete] = useState<Category | null>(null);
    const [deleting, setDeleting] = useState(false);

    const canEdit = auth.can.edit_category;
    const canDelete = auth.can.delete_category;

    const columns = useMemo(
        () => [
            columnHelper.accessor('name', { header: 'Name' }),
            columnHelper.accessor('slug', { header: 'Slug' }),
            columnHelper.display({
                id: 'parent',
                header: 'Parent',
                cell: ({ row }) =>
                    row.original.parent?.name ?? (
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
                                          <Link href={editCategory(row.original).url}>Edit</Link>
                                      </Button>
                                  )}
                                  {canDelete && (
                                      <Button
                                          variant="outline"
                                          size="sm"
                                          onClick={(e) => {
                                              e.stopPropagation();
                                              setCategoryToDelete(row.original);
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
                adminCategoriesRoute.url(),
                { search: search || undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [search, filters.search]);

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: categories.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function goToPage(url: string | null) {
        // eslint-disable-next-line
        if (!url) return;

        router.get(url, {}, { preserveState: true });
    }

    function handleDelete() {
        if (!categoryToDelete) return;

        setDeleting(true);

        router.delete(destroyCategory(categoryToDelete).url, {
            onSuccess: () => {
                setCategoryToDelete(null);
                setDeleting(false);
            },
            onError: () => setDeleting(false),
        });
    }

    const pageLinks = categories.links.filter(
        (link) => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;',
    );

    return (
        <>
            <Head title="Categories" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Input
                        placeholder="Search by name or slug..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                    {auth.can.create_category && (
                        <Button asChild>
                            <Link href={categoriesCreateRoute.url()}>Create Category</Link>
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
                                        No categories found.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        {categories.from
                            ? `Showing ${categories.from}–${categories.to} of ${categories.total}`
                            : 'No results'}
                    </span>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!categories.prev_page_url}
                            onClick={() => goToPage(categories.prev_page_url)}
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
                            disabled={!categories.next_page_url}
                            onClick={() => goToPage(categories.next_page_url)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={categoryToDelete !== null}
                onOpenChange={(open) => { if (!open) setCategoryToDelete(null); }}
            >
                <DialogContent aria-describedby={undefined}>
                    <DialogTitle>Delete Category</DialogTitle>
                    <Alert variant="destructive">
                        <AlertTitle>Are you sure?</AlertTitle>
                        <AlertDescription>
                            <strong>{categoryToDelete?.name}</strong> will be permanently deleted.
                            Child categories will become root categories.
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

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: adminCategoriesRoute.url() }],
};
