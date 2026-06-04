import type { Permission } from '@/types';

const dependencies: Record<string, string[]> = {
    create_user: ['view_users'],
    edit_user: ['view_users'],
    delete_user: ['view_users'],
};

export function resolveToggle(
    permission: Permission,
    isGranted: boolean,
    allPermissions: Permission[],
    grantedIds: Set<number>,
): { toGrant: Permission[]; toRevoke: Permission[] } {
    if (isGranted) {
        const dependents = allPermissions.filter(
            (p) => dependencies[p.name]?.includes(permission.name) && grantedIds.has(p.id),
        );

        return { toGrant: [], toRevoke: [...dependents, permission] };
    } else {
        const required = (dependencies[permission.name] ?? [])
            .map((name) => allPermissions.find((p) => p.name === name))
            .filter((p): p is Permission => p !== undefined && !grantedIds.has(p.id));

        return { toGrant: [...required, permission], toRevoke: [] };
    }
}

export function isPermissionDisabled(
    permission: Permission,
    allPermissions: Permission[],
    grantedIds: Set<number>,
): boolean {
    return (dependencies[permission.name] ?? []).some((depName) => {
        const dep = allPermissions.find((p) => p.name === depName);
        return dep !== undefined && !grantedIds.has(dep.id);
    });
}
