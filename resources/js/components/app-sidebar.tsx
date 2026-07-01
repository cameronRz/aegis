import { Link, usePage } from '@inertiajs/react';
import { Bot, ClipboardList, FileText, HeadphonesIcon, LayoutGrid, Package, Receipt, RefreshCcw, Settings2, ShieldCheck, ShoppingBag, ShoppingCart, Tag, UserPlus, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cart as cartRoute, dashboard, orders as ordersRoute, shop as shopRoute, subscriptions as subscriptionsRoute } from '@/routes';
import {
    categories as adminCategoriesRoute,
    documents as adminDocumentsRoute,
    invitations as adminInvitationsRoute,
    orders as adminOrdersRoute,
    roles as adminRolesRoute,
    products as adminProductsRoute,
    users as adminUsersRoute,
} from '@/routes/admin';
import { index as adminSupportRoute } from '@/routes/admin/support';
import { index as aiRoute } from '@/routes/ai';
import { index as supportRoute } from '@/routes/support';
import type { NavItem } from '@/types';
import type { Features } from '@/types/auth';

const footerNavItems: NavItem[] = [
    // TODO: Revisit this section later
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/laravel/react-starter-kit',
    //     icon: FolderGit2,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#react',
    //     icon: BookOpen,
    // },
];

function ALL_NAV_ITEMS(cartItemCount: number, unreadSupportCount: number): NavItem[] {
    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'AI Assistant',
            href: aiRoute.url(),
            icon: Bot,
            permission: 'use_ai_assistant',
            featureFlag: 'aiAssistantEnabled',
        },
        {
            title: 'Shop',
            href: shopRoute.url(),
            icon: ShoppingBag,
        },
        {
            title: 'Cart',
            href: cartRoute.url(),
            icon: ShoppingCart,
            badge: cartItemCount || undefined,
            // Cart state changes via POSTs from other pages, so prefetching would
            // serve a stale cached version after items are added.
            prefetch: false,
        },
        {
            title: 'Orders',
            href: ordersRoute.url(),
            icon: Receipt,
        },
        {
            title: 'Subscriptions',
            href: subscriptionsRoute.url(),
            icon: RefreshCcw,
        },
        {
            title: 'Support',
            href: supportRoute.url(),
            icon: HeadphonesIcon,
            permission: 'use_support',
            featureFlag: 'supportChatEnabled',
        },
        {
            title: 'Users',
            href: adminUsersRoute.url(),
            icon: Users,
            permission: 'view_users',
        },
        {
            title: 'Categories',
            href: adminCategoriesRoute.url(),
            icon: Tag,
            permission: 'view_categories',
        },
        {
            title: 'Products',
            href: adminProductsRoute.url(),
            icon: Package,
            permission: 'view_products',
        },
        {
            title: 'Sales',
            href: adminOrdersRoute.url(),
            icon: ClipboardList,
            permission: 'admin',
        },
        {
            title: 'Documents',
            href: adminDocumentsRoute.url(),
            icon: FileText,
            permission: 'admin',
        },
        {
            title: 'Support Queue',
            href: adminSupportRoute.url(),
            icon: HeadphonesIcon,
            permission: 'handle_support',
            badge: unreadSupportCount || undefined,
        },
        {
            title: 'Invitations',
            href: adminInvitationsRoute.url(),
            icon: UserPlus,
            permission: 'admin',
        },
        {
            title: 'Roles',
            href: adminRolesRoute.url(),
            icon: ShieldCheck,
            permission: 'admin',
        },
        {
            title: 'Settings',
            href: '/admin/settings/features',
            icon: Settings2,
            permission: 'admin',
        },
    ];
}

export function AppSidebar() {
    const { auth, cartItemCount, unreadSupportCount, features } = usePage<{
        auth: { can: Record<string, boolean> };
        cartItemCount: number;
        unreadSupportCount: number;
        features: Features;
    }>().props;

    const mainNavItems = ALL_NAV_ITEMS(cartItemCount, unreadSupportCount).filter(
        (item) =>
            (!item.permission || auth.can[item.permission]) &&
            (!item.featureFlag || features[item.featureFlag]),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
