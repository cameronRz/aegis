import InputError from '@/components/input-error';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PermissionSet, Role } from '@/types';

export type UserFormData = {
    first_name: string;
    last_name: string;
    email: string;
    role: Role;
    permission_set_id: number | null;
};

const roleLabels: Record<Role, string> = {
    site_admin: 'Site Admin',
    admin: 'Admin',
    user: 'User',
};

type Props = {
    data: UserFormData;
    setData: <K extends keyof UserFormData>(key: K, value: UserFormData[K]) => void;
    errors: Partial<Record<keyof UserFormData, string>>;
    availableRoles: Role[];
    permissionSets: PermissionSet[];
};

export function UserFormFields({ data, setData, errors, availableRoles, permissionSets }: Props) {
    const selectedSet = permissionSets.find((s) => s.id === data.permission_set_id);

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

            <Card>
                <CardHeader>
                    <CardTitle>Permission Set</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="grid gap-2">
                        <Label htmlFor="permission_set_id">Assigned set</Label>
                        <Select
                            value={data.permission_set_id?.toString() ?? 'none'}
                            onValueChange={(value) =>
                                setData('permission_set_id', value === 'none' ? null : Number(value))
                            }
                        >
                            <SelectTrigger id="permission_set_id" className="w-64">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No set</SelectItem>
                                {permissionSets.map((set) => (
                                    <SelectItem key={set.id} value={set.id.toString()}>
                                        {set.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {selectedSet?.permissions?.length ? (
                        <p className="text-muted-foreground text-xs">
                            Grants:{' '}
                            {selectedSet.permissions.map((p) => p.display_name).join(', ')}
                        </p>
                    ) : data.permission_set_id === null ? (
                        <p className="text-muted-foreground text-xs">
                            No permissions beyond profile and shop access.
                        </p>
                    ) : null}
                </CardContent>
            </Card>
        </>
    );
}
