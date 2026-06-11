import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { destroy as destroyUser, edit as editUser } from '@/actions/App/Http/Controllers/UserController';
import { toggle as togglePermission } from '@/actions/App/Http/Controllers/UserPermissionController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { RoleBadge } from '@/components/role-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { users as adminUsersRoute } from '@/routes/admin';
import { PRIVILEGED_ROLES } from '@/types';
import type { Permission, User } from '@/types';
import { isPermissionDisabled, resolveToggle } from './permission-dependencies';

type Props = {
    user: User & { permissions: Permission[] };
    allPermissions: Permission[];
    canEdit: boolean;
    canDelete: boolean;
    canManagePermissions: boolean;
};

export default function UserShow({ user, allPermissions, canEdit, canDelete, canManagePermissions }: Props) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const grantedIds = new Set(user.permissions.map((p) => p.id));
    const isPrivileged = PRIVILEGED_ROLES.includes(user.role);

    function fireToggle(permission: Permission, onSuccess?: () => void) {
        router.post(
            togglePermission({ user: user.id, permission: permission.id }).url,
            {},
            { preserveScroll: true, onSuccess },
        );
    }

    function handleToggle(permission: Permission) {
        const { toGrant, toRevoke } = resolveToggle(
            permission,
            grantedIds.has(permission.id),
            allPermissions,
            grantedIds,
        );
        const sequence = [...toRevoke, ...toGrant];
        const fire = (index: number) => {
            if (index >= sequence.length) {
                toast('Permission updated');

                return;
            }

            fireToggle(sequence[index], () => fire(index + 1));
        };
        fire(0);
    }

    function handleDelete() {
        setDeleting(true);
        router.delete(destroyUser(user).url, {
            onSuccess: () => setDeleteOpen(false),
            onFinish: () => setDeleting(false),
        });
    }

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
                        <CardTitle>Permissions</CardTitle>
                        <CardDescription>
                            {isPrivileged
                                ? 'Admins and site admins have all permissions by default.'
                                : canManagePermissions
                                  ? 'Toggle permissions for this user. Changes apply immediately.'
                                  : 'You can view but not change permissions for this user.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {isPrivileged ? (
                            <p className="text-sm text-muted-foreground">
                                This user's role grants full access. Individual permission toggles are not
                                applicable.
                            </p>
                        ) : (
                            <div className="flex flex-col">
                                {allPermissions.map((permission, index) => (
                                    <div key={permission.id}>
                                        {index > 0 && <Separator className="my-4" />}
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex flex-col gap-1">
                                                <Label
                                                    htmlFor={`permission-${permission.id}`}
                                                    className="font-medium"
                                                >
                                                    {permission.display_name}
                                                </Label>
                                                {permission.description && (
                                                    <p className="text-sm text-muted-foreground">
                                                        {permission.description}
                                                    </p>
                                                )}
                                            </div>
                                            <Checkbox
                                                id={`permission-${permission.id}`}
                                                checked={grantedIds.has(permission.id)}
                                                disabled={!canManagePermissions || isPermissionDisabled(permission, allPermissions, grantedIds)}
                                                onCheckedChange={() => handleToggle(permission)}
                                            />
                                        </div>
                                    </div>
                                ))}
                                {allPermissions.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No permissions have been defined yet.
                                    </p>
                                )}
                            </div>
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
