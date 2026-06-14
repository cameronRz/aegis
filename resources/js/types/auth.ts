export type Tier = 'site_admin' | 'admin' | 'user';

export const PRIVILEGED_TIERS: Tier[] = ['site_admin', 'admin'];

export type Permission = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    created_at: string;
    updated_at: string;
};

export type Role = {
    id: number;
    name: string;
    description: string | null;
    permissions?: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
};

export type User = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    tier: Tier;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles?: Role[];
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Can = {
    admin: boolean;
    view_users: boolean;
    create_user: boolean;
    edit_user: boolean;
    delete_user: boolean;
    view_categories: boolean;
    create_category: boolean;
    edit_category: boolean;
    delete_category: boolean;
    view_products: boolean;
    create_product: boolean;
    edit_product: boolean;
    delete_product: boolean;
    [key: string]: boolean;
};

export type Category = {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    parent?: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
};

export type ProductType = 'physical' | 'digital' | 'subscription';
export type PriceType = 'one_time' | 'recurring';
export type BillingInterval = 'weekly' | 'monthly' | 'yearly';

export type Product = {
    id: number;
    category_id: number | null;
    name: string;
    type: ProductType;
    sku: string;
    is_active: boolean;
    description: string;
    price: number;
    price_type: PriceType;
    billing_interval: BillingInterval | null;
    billing_interval_count: number | null;
    trial_period_days: number | null;
    stock_quantity: number | null;
    track_inventory: boolean;
    sort_order: number;
    image: string | null;
    category?: { id: number; name: string } | null;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
};

export type Auth = {
    user: User;
    can: Can;
};

export type CartItem = {
    id: number;
    cart_id: number;
    product_id: number | null;
    quantity: number;
    product?: Product | null;
    created_at: string;
    updated_at: string;
};

export type Cart = {
    id: number;
    user_id: number | null;
    items: CartItem[];
    created_at: string;
    updated_at: string;
};

export type OrderStatus = 'pending' | 'paid' | 'failed' | 'refunded' | 'expired';

export type OrderItem = {
    id: number;
    order_id: number;
    product_id: number | null;
    product_name: string;
    product_sku: string;
    product_type: string;
    price: number; // cents
    quantity: number;
    product?: Product | null;
    created_at: string;
    updated_at: string;
};

export type Order = {
    id: number;
    order_number: string;
    user_id: number | null;
    status: OrderStatus;
    subtotal: number; // cents
    total: number; // cents
    stripe_checkout_session_id: string | null;
    stripe_payment_intent_id: string | null;
    items?: OrderItem[];
    user?: User | null;
    created_at: string;
    updated_at: string;
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
