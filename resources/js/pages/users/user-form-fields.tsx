import InputError from '@/components/input-error';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { PRIVILEGED_ROLES } from '@/types';
import type { Permission, Role } from '@/types';
import { isPermissionDisabled, resolveToggle } from './permission-dependencies';

export type UserFormData = {
    first_name: string;
    last_name: string;
    email: string;
    role: Role;
    permissions: number[];
};

const roleLabels: Record<Role, string> = {
    site_admin: 'Site Admin',
    admin: 'Admin',
    manager: 'Manager',
    user: 'User',
};

type Props = {
    data: UserFormData;
    setData: <K extends keyof UserFormData>(key: K, value: UserFormData[K]) => void;
    errors: Partial<Record<keyof UserFormData, string>>;
    availableRoles: Role[];
    allPermissions: Permission[];
    canAssignPermissions: boolean;
    permissionsDescription?: string;
};

export function UserFormFields({
    data,
    setData,
    errors,
    availableRoles,
    allPermissions,
    canAssignPermissions,
    permissionsDescription = 'Assign or revoke permissions for this user.',
}: Props) {
    const grantedIds = new Set(data.permissions);

    function togglePermission(id: number) {
        const permission = allPermissions.find((p) => p.id === id)!;
        const { toGrant, toRevoke } = resolveToggle(
            permission,
            grantedIds.has(id),
            allPermissions,
            grantedIds,
        );
        const updated = data.permissions
            .filter((p) => !toRevoke.some((r) => r.id === p))
            .concat(toGrant.map((p) => p.id));
        setData('permissions', updated);
    }

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>User Details</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First name</Label>
                            <Input
                                id="first_name"
                                name="first_name"
                                value={data.first_name}
                                onChange={(e) => setData('first_name', e.target.value)}
                                placeholder="First name"
                                autoComplete="off"
                                required
                            />
                            <InputError message={errors.first_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                name="last_name"
                                value={data.last_name}
                                onChange={(e) => setData('last_name', e.target.value)}
                                placeholder="Last name"
                                autoComplete="off"
                                required
                            />
                            <InputError message={errors.last_name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="Email address"
                            autoComplete="off"
                            required
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <Select
                            value={data.role}
                            onValueChange={(value) => setData('role', value as Role)}
                        >
                            <SelectTrigger id="role" className="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {availableRoles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {roleLabels[role]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} />
                    </div>
                </CardContent>
            </Card>

            {canAssignPermissions && (
                <Card className="max-w-lg">
                    <CardHeader>
                        <CardTitle>Permissions</CardTitle>
                        <CardDescription>{permissionsDescription}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {PRIVILEGED_ROLES.includes(data.role) ? (
                            <p className="text-sm text-muted-foreground">
                                Site admins and admins have all permissions by default.
                                Individual permissions are not applicable.
                            </p>
                        ) : allPermissions.length > 0 ? (
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
                                                checked={data.permissions.includes(permission.id)}
                                                disabled={isPermissionDisabled(permission, allPermissions, grantedIds)}
                                                onCheckedChange={() => togglePermission(permission.id)}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No permissions have been defined yet.
                            </p>
                        )}
                    </CardContent>
                </Card>
            )}
        </>
    );
}
