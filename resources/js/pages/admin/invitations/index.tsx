import { Form, Head, router } from '@inertiajs/react';
import {
    createColumnHelper,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTablePagination } from '@/components/data-table-pagination';
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
import { invitations as invitationsRoute } from '@/routes/admin';
import {
    destroy as destroyInvitation,
    resend as resendInvitation,
    store as storeInvitation,
} from '@/routes/admin/invitations';
import type { Invitation, PaginatedData } from '@/types';

type Props = {
    invitations: PaginatedData<Invitation>;
};

const columnHelper = createColumnHelper<Invitation>();

type TableProps = {
    invitations: PaginatedData<Invitation>;
    resendingId: number | null;
    setResendingId: (id: number | null) => void;
    setInvitationToRevoke: (invitation: Invitation) => void;
};

function InvitationsTable({ invitations, resendingId, setResendingId, setInvitationToRevoke }: TableProps) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('email', { header: 'Email' }),
            columnHelper.display({
                id: 'invited_by',
                header: 'Invited By',
                cell: ({ row }) =>
                    row.original.inviter?.full_name ?? (
                        <span className="text-muted-foreground">—</span>
                    ),
            }),
            columnHelper.display({
                id: 'sent_at',
                header: 'Sent',
                cell: ({ row }) =>
                    new Date(row.original.created_at).toLocaleDateString(),
            }),
            columnHelper.display({
                id: 'expires_at',
                header: 'Expires',
                cell: ({ row }) => {
                    const expires = new Date(row.original.created_at);
                    expires.setDate(expires.getDate() + 7);
                    const isExpired = expires < new Date();

                    return (
                        <span className={isExpired ? 'text-destructive' : ''}>
                            {expires.toLocaleDateString()}
                        </span>
                    );
                },
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={resendingId === row.original.id}
                            onClick={(e) => {
                                e.stopPropagation();
                                setResendingId(row.original.id);
                                router.post(
                                    resendInvitation(row.original).url,
                                    {},
                                    { onFinish: () => setResendingId(null) },
                                );
                            }}
                        >
                            Resend
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                setInvitationToRevoke(row.original);
                            }}
                        >
                            Revoke
                        </Button>
                    </div>
                ),
            }),
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [resendingId],
    );

    // eslint-disable-next-line react-hooks/incompatible-library
    const table = useReactTable({
        data: invitations.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <DataTable table={table} emptyMessage="No pending invitations." />
            <DataTablePagination paginatedData={invitations} />
        </>
    );
}

export default function InvitationsIndex({ invitations }: Props) {
    const [inviteOpen, setInviteOpen] = useState(false);
    const [invitationToRevoke, setInvitationToRevoke] = useState<Invitation | null>(null);
    const [revoking, setRevoking] = useState(false);
    const [resendingId, setResendingId] = useState<number | null>(null);

    /**
     * TanStack Table v8 doesn't re-render when `data` changes via React 19 prop updates (see
     * eslint-disable incompatible-library above). Changing the key forces InvitationsTable to
     * remount, giving useReactTable a fresh start with the new data.
     */
    const tableKey = invitations.data.map((i) => `${i.id}:${i.created_at}`).join(',');

    function handleRevoke() {
        if (!invitationToRevoke) return;

        setRevoking(true);
        router.delete(destroyInvitation(invitationToRevoke).url, {
            onFinish: () => {
                setRevoking(false);
                setInvitationToRevoke(null);
            },
        });
    }

    return (
        <>
            <Head title="Invitations" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Button onClick={() => setInviteOpen(true)}>Invite Client</Button>
                </div>

                <InvitationsTable
                    key={tableKey}
                    invitations={invitations}
                    resendingId={resendingId}
                    setResendingId={setResendingId}
                    setInvitationToRevoke={setInvitationToRevoke}
                />
            </div>

            <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                <DialogContent aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>Invite Client</DialogTitle>
                        <DialogDescription>
                            Send an invitation email to a new client.
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        action={storeInvitation().url}
                        method="post"
                        resetOnSuccess={['email']}
                        onSuccess={() => setInviteOpen(false)}
                        className="flex flex-col gap-4 py-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        placeholder="client@example.com"
                                        autoFocus
                                    />
                                    {errors.email && (
                                        <p className="text-destructive text-sm" role="alert">
                                            {errors.email}
                                        </p>
                                    )}
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setInviteOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Send Invitation
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={invitationToRevoke !== null}
                onOpenChange={(open) => {
                    if (!open) setInvitationToRevoke(null);
                }}
                title="Revoke Invitation"
                description={
                    <>
                        The invitation for <strong>{invitationToRevoke?.email}</strong> will be
                        revoked and the link will no longer work.
                    </>
                }
                confirmLabel="Revoke"
                processing={revoking}
                onConfirm={handleRevoke}
            />
        </>
    );
}

InvitationsIndex.layout = {
    breadcrumbs: [{ title: 'Invitations', href: invitationsRoute.url() }],
};
