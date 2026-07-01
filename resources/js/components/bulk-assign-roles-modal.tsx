import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { bulkAssignRoles } from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Role } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: Role[];
    selectedUserIds: number[];
    onSuccess: () => void;
};

export function BulkAssignRolesModal({ open, onOpenChange, roles, selectedUserIds, onSuccess }: Props) {
    const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    function handleOpenChange(newOpen: boolean) {
        if (!newOpen) setSelectedRoleId(null);

        onOpenChange(newOpen);
    }

    function handleAssign() {
        if (!selectedRoleId) return;

        setProcessing(true);
        router.post(
            bulkAssignRoles.url(),
            { user_ids: selectedUserIds, role_ids: [selectedRoleId] },
            {
                onSuccess: () => {
                    const count = selectedUserIds.length;
                    toast.success(`Role assigned to ${count} user${count !== 1 ? 's' : ''}.`);
                    onOpenChange(false);
                    onSuccess();
                },
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogTitle>Assign Role</DialogTitle>
                <DialogDescription>
                    Select a role to assign to {selectedUserIds.length} selected user
                    {selectedUserIds.length !== 1 ? 's' : ''}. Existing roles will not be removed.
                </DialogDescription>
                <Select
                    value={selectedRoleId !== null ? selectedRoleId.toString() : undefined}
                    onValueChange={(val) => setSelectedRoleId(Number(val))}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Select a role..." />
                    </SelectTrigger>
                    <SelectContent>
                        {roles.map((role) => (
                            <SelectItem key={role.id} value={role.id.toString()}>
                                {role.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <DialogFooter>
                    <Button variant="outline" onClick={() => handleOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button disabled={!selectedRoleId || processing} onClick={handleAssign}>
                        {processing ? 'Assigning...' : 'Assign'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
