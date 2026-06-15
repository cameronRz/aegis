import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Package, Receipt, RefreshCcw, ShieldCheck, ShoppingBag, ShoppingCart, Tag, Users } from 'lucide-react';
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
    roles as adminRolesRoute,
    products as adminProductsRoute,
    users as adminUsersRoute,
} from '@/routes/admin';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

function ALL_NAV_ITEMS(cartItemCount: number): NavItem[] {
    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
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
            title: 'Roles',
            href: adminRolesRoute.url(),
            icon: ShieldCheck,
            permission: 'admin',
        },
    ];
}

export function AppSidebar() {
    const { auth, cartItemCount } = usePage<{ auth: { can: Record<string, boolean> }; cartItemCount: number }>().props;

    const mainNavItems = ALL_NAV_ITEMS(cartItemCount).filter(
        (item) => !item.permission || auth.can[item.permission],
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
