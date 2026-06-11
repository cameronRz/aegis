import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useState, useMemo } from 'react';
import {
    destroy as destroyCategory,
    edit as editCategory,
} from '@/actions/App/Http/Controllers/CategoryController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedSearch } from '@/hooks/use-debounced-search';
import { categories as adminCategoriesRoute } from '@/routes/admin';
import { create as categoriesCreateRoute } from '@/routes/admin/categories';
import type { Auth, Category, PaginatedData } from '@/types';

type Props = {
    categories: PaginatedData<Category>;
    filters: { search?: string };
};

const columnHelper = createColumnHelper<Category>();

type PageProps = { auth: Auth };

export default function CategoriesIndex({ categories, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useDebouncedSearch(filters.search, adminCategoriesRoute.url());
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

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: categories.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

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

                <DataTable table={table} emptyMessage="No categories found." />

                <DataTablePagination paginatedData={categories} />
            </div>

            <ConfirmDialog
                open={categoryToDelete !== null}
                onOpenChange={(open) => { if (!open) setCategoryToDelete(null); }}
                title="Delete Category"
                description={
                    <>
                        <strong>{categoryToDelete?.name}</strong> will be permanently deleted.
                        Child categories will become root categories.
                    </>
                }
                processing={deleting}
                onConfirm={handleDelete}
            />
        </>
    );
}

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: adminCategoriesRoute.url() }],
};
