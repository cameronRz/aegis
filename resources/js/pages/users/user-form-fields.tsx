import InputError from '@/components/input-error';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { Role, Tier } from '@/types';

export type UserFormData = {
    first_name: string;
    last_name: string;
    email: string;
    tier: Tier;
    role_ids: number[];
};

const tierLabels: Record<Tier, string> = {
    site_admin: 'Site Admin',
    admin: 'Admin',
    user: 'User',
};

type Props = {
    data: UserFormData;
    setData: <K extends keyof UserFormData>(key: K, value: UserFormData[K]) => void;
    errors: Partial<Record<keyof UserFormData, string>>;
    availableTiers: Tier[];
    roles: Role[];
};

function toggleId(ids: number[], id: number): number[] {
    return ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id];
}

export function UserFormFields({ data, setData, errors, availableTiers, roles }: Props) {
    const selectedRoles = roles.filter((r) => data.role_ids.includes(r.id));
    const combinedPermissions = [
        ...new Set(
            selectedRoles.flatMap((r) => r.permissions?.map((p) => p.display_name) ?? []),
        ),
    ];

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

                    {availableTiers.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="tier">Access tier</Label>
                            <Select
                                value={data.tier}
                                onValueChange={(value) => setData('tier', value as Tier)}
                            >
                                <SelectTrigger id="tier" className="w-48">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableTiers.map((tier) => (
                                        <SelectItem key={tier} value={tier}>
                                            {tierLabels[tier]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tier} />
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Roles</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {roles.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No roles defined yet.</p>
                    ) : (
                        <div className="space-y-2">
                            {roles.map((r) => (
                                <div key={r.id} className="flex items-center gap-3">
                                    <Checkbox
                                        id={`role-${r.id}`}
                                        checked={data.role_ids.includes(r.id)}
                                        onCheckedChange={() =>
                                            setData('role_ids', toggleId(data.role_ids, r.id))
                                        }
                                    />
                                    <Label
                                        htmlFor={`role-${r.id}`}
                                        className="cursor-pointer font-normal"
                                    >
                                        {r.name}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    )}

                    {combinedPermissions.length > 0 && (
                        <p className="text-muted-foreground text-xs">
                            Grants: {combinedPermissions.join(', ')}
                        </p>
                    )}
                </CardContent>
            </Card>
        </>
    );
}
