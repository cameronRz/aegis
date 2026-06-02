export type Role = 'site_admin' | 'admin' | 'manager' | 'user';

export type Permission = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
};

export type User = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    role: Role;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Can = {
    view_users: boolean;
    [key: string]: boolean;
};

export type Auth = {
    user: User;
    can: Can;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
