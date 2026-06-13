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
import type { Role } from '@/types';

export type UserFormData = {
    first_name: string;
    last_name: string;
    email: string;
    role: Role;
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
};

export function UserFormFields({ data, setData, errors, availableRoles }: Props) {
    return (
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
    );
}
