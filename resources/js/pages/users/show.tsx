import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';

import { edit as editUser } from '@/actions/App/Http/Controllers/UserController';
import { toggle as togglePermission } from '@/actions/App/Http/Controllers/UserPermissionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { users as adminUsersRoute } from '@/routes/admin';
import type { Auth, Permission, Role, User } from '@/types';
import { isPermissionDisabled, resolveToggle } from './permission-dependencies';

type Props = {
    user: User & { permissions: Permission[] };
    allPermissions: Permission[];
    canManagePermissions: boolean;
};

const roleConfig: Record<
    Role,
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    site_admin: { label: 'Site Admin', variant: 'destructive' },
    admin: { label: 'Admin', variant: 'default' },
    manager: { label: 'Manager', variant: 'secondary' },
    user: { label: 'User', variant: 'outline' },
};

const privilegedRoles: Role[] = ['site_admin', 'admin'];

type PageProps = { auth: Auth };

export default function UserShow({ user, allPermissions, canManagePermissions }: Props) {
    const { auth } = usePage<PageProps>().props;
    const { label, variant } = roleConfig[user.role];

    const canEditUser =
        auth.can.edit_user &&
        auth.user.id !== user.id &&
        (auth.user.role === 'site_admin' || !privilegedRoles.includes(user.role));
    const grantedIds = new Set(user.permissions.map((p) => p.id));
    const isPrivileged = privilegedRoles.includes(user.role);

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
                                <Badge variant={variant}>{label}</Badge>
                                {canEditUser && (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={editUser(user).url}>Edit</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardHeader>
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
        </>
    );
}

UserShow.layout = {
    breadcrumbs: [
        { title: 'Users', href: adminUsersRoute.url() },
        { title: 'User Details' },
    ],
};
