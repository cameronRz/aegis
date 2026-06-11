import { Link, usePage } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Package, ShoppingBag, Tag, Users } from 'lucide-react';
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
import { dashboard, shop as shopRoute } from '@/routes';
import {
    categories as adminCategoriesRoute,
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

const ALL_NAV_ITEMS: NavItem[] = [
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
];

export function AppSidebar() {
    const { auth } = usePage().props;

    const mainNavItems = ALL_NAV_ITEMS.filter((item) => !item.permission || auth.can[item.permission]);

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
