import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { destroy as destroyUser, edit as editUser } from '@/actions/App/Http/Controllers/UserController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { users as adminUsersRoute } from '@/routes/admin';
import type { User } from '@/types';

type Props = {
    user: User;
    canEdit: boolean;
    canDelete: boolean;
};

export default function UserShow({ user, canEdit, canDelete }: Props) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    function handleDelete() {
        setDeleting(true);
        router.delete(destroyUser(user).url, {
            onSuccess: () => setDeleteOpen(false),
            onFinish: () => setDeleting(false),
        });
    }

    const permissionSet = user.permission_set;

    return (
        <>
            <Head title={user.full_name} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex flex-col gap-1.5">
                                <CardTitle className="text-xl">{user.full_name}</CardTitle>
                                <CardDescription>{user.email}</CardDescription>
                            </div>
                            <div className="flex items-center gap-3">
                                <RoleBadge role={user.role} />
                                {canEdit && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={editUser(user).url}>Edit</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    {canDelete && (
                        <CardContent className="pt-0">
                            <button
                                onClick={() => setDeleteOpen(true)}
                                className="text-muted-foreground hover:text-destructive text-sm transition-colors"
                            >
                                Delete user
                            </button>
                        </CardContent>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Permission Set</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {permissionSet ? (
                            <div className="flex flex-col gap-1">
                                <span className="font-medium">{permissionSet.name}</span>
                                {permissionSet.permissions?.length ? (
                                    <span className="text-muted-foreground text-sm">
                                        {permissionSet.permissions
                                            .map((p) => p.display_name)
                                            .join(', ')}
                                    </span>
                                ) : null}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">No permission set assigned.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title={`Delete ${user.full_name}`}
                description={`This will permanently delete ${user.full_name}'s account and cannot be undone.`}
                confirmLabel="Delete user"
                processing={deleting}
                onConfirm={handleDelete}
            />
        </>
    );
}

UserShow.layout = {
    breadcrumbs: [
        { title: 'Users', href: adminUsersRoute.url() },
        { title: 'User Details' },
    ],
};
