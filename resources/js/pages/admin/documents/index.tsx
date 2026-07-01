import { Head, router, useForm, usePoll } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { Loader2 } from 'lucide-react';
import type { SubmitEvent } from 'react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { documents as documentsRoute } from '@/routes/admin';
import { destroy as destroyDocument, store as storeDocument } from '@/routes/admin/documents';
import type { KbDocument, PaginatedData } from '@/types';

type PageDocument = KbDocument & { user: Pick<KbDocument['user'] & object, never> } & {
    user: Pick<NonNullable<KbDocument['user']>, 'full_name'>;
};

type Props = {
    documents: PaginatedData<PageDocument>;
};

const statusConfig = {
    processing: { label: 'Processing', variant: 'secondary' as const, spinning: true },
    ready: { label: 'Ready', variant: 'default' as const, spinning: false },
    failed: { label: 'Failed', variant: 'destructive' as const, spinning: false },
};

const columnHelper = createColumnHelper<PageDocument>();

type TableProps = {
    documents: PaginatedData<PageDocument>;
    onDelete: (doc: PageDocument) => void;
};

function DocumentsTable({ documents, onDelete }: TableProps) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('title', { header: 'Title' }),
            columnHelper.accessor('original_filename', { header: 'File' }),
            columnHelper.display({
                id: 'status',
                header: 'Status',
                cell: ({ row }) => {
                    const config = statusConfig[row.original.status];

                    return (
                        <Badge variant={config.variant} className="gap-1.5">
                            {config.spinning && (
                                <Loader2 className="h-3 w-3 animate-spin" />
                            )}
                            {config.label}
                        </Badge>
                    );
                },
            }),
            columnHelper.display({
                id: 'uploaded_by',
                header: 'Uploaded By',
                cell: ({ row }) =>
                    row.original.user?.full_name ?? (
                        <span className="text-muted-foreground">—</span>
                    ),
            }),
            columnHelper.display({
                id: 'date',
                header: 'Date',
                cell: ({ row }) =>
                    new Date(row.original.created_at).toLocaleDateString(),
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <div className="flex justify-end">
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
        data: documents.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <DataTable table={table} emptyMessage="No documents uploaded yet." />
            <DataTablePagination paginatedData={documents} />
        </>
    );
}

export default function DocumentsIndex({ documents }: Props) {
    const [uploadOpen, setUploadOpen] = useState(false);
    const [documentToDelete, setDocumentToDelete] = useState<PageDocument | null>(null);
    const [deleting, setDeleting] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        file: null as File | null,
    });

    usePoll(3000, { only: ['documents'] });

    const tableKey = documents.data.map((d) => `${d.id}:${d.status}`).join(',');

    function handleUpload(e: SubmitEvent<HTMLFormElement>) {
        e.preventDefault();
        post(storeDocument.url(), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setUploadOpen(false);
            },
        });
    }

    function handleDelete() {
        if (!documentToDelete) return;

        setDeleting(true);
        router.delete(destroyDocument(documentToDelete).url, {
            onFinish: () => {
                setDeleting(false);
                setDocumentToDelete(null);
            },
        });
    }

    return (
        <>
            <Head title="Documents" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Button onClick={() => setUploadOpen(true)}>Upload Document</Button>
                </div>

                <DocumentsTable
                    key={tableKey}
                    documents={documents}
                    onDelete={setDocumentToDelete}
                />
            </div>

            <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
                <DialogContent aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>Upload Document</DialogTitle>
                        <DialogDescription>
                            Upload a PDF or TXT file to add to the AI knowledge base.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUpload} className="flex flex-col gap-4 py-4">
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="e.g. Product Care Guide"
                                autoFocus
                            />
                            {errors.title && (
                                <p className="text-destructive text-sm" role="alert">
                                    {errors.title}
                                </p>
                            )}
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="file">File</Label>
                            <Input
                                id="file"
                                type="file"
                                accept=".pdf,.txt"
                                onChange={(e) =>
                                    setData('file', e.target.files?.[0] ?? null)
                                }
                            />
                            {errors.file && (
                                <p className="text-destructive text-sm" role="alert">
                                    {errors.file}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setUploadOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Upload
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={documentToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) setDocumentToDelete(null);
                }}
                title="Delete Document"
                description={
                    <>
                        <strong>{documentToDelete?.title}</strong> and all its chunks will be
                        permanently deleted. The AI assistant will no longer have access to this
                        content.
                    </>
                }
                confirmLabel="Delete"
                processing={deleting}
                onConfirm={handleDelete}
            />
        </>
    );
}

DocumentsIndex.layout = {
    breadcrumbs: [{ title: 'Documents', href: documentsRoute.url() }],
};
