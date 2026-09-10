import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, BookOpen, HardDrive, LayoutGrid, Library, Palette, ScanSearch, Sparkles } from 'lucide-react';
import AppLogo from './app-logo';

const dashboardNavItem: NavItem = {
    title: 'Dashboard',
    url: '/dashboard',
    icon: LayoutGrid,
};

/** Creating and administering journals — structural, so it sits alongside CMS rather than in it. */
const journalsNavItem: NavItem = {
    title: 'Journals',
    url: '/admin/journals',
    icon: Library,
};

/** Provider credentials, the installation AI switch, and the ai_runs audit trail. Site-admin only. */
const aiNavItem: NavItem = {
    title: 'AI',
    url: '/admin/ai',
    icon: Sparkles,
};

/** How review and editorial messages actually get delivered. Site-admin only. */
const notificationsNavItem: NavItem = {
    title: 'Notifications',
    url: '/admin/notifications',
    icon: Bell,
};

/** Deposit credentials for Crossref and DataCite, and the live/test endpoint. Site-admin only. */
const indexingNavItem: NavItem = {
    title: 'Indexing',
    url: '/admin/indexing',
    icon: ScanSearch,
};

/** Where uploaded files are kept, and moving them to a bucket. Site-admin only. */
const storageNavItem: NavItem = {
    title: 'Storage',
    url: '/admin/storage',
    icon: HardDrive,
};

/**
 * Landing-page content. Site-admin only — every /cms route is gated by SitePolicy::manage, so
 * showing this to anyone else would just be a menu item that 403s on click.
 */
const cmsNavItem: NavItem = {
    title: 'CMS',
    url: '/cms',
    icon: Palette,
    items: [
        { title: 'Appearance', url: '/cms/appearance' },
        { title: 'Homepage', url: '/cms/homepage' },
        { title: 'Hero slides', url: '/cms/hero-slides' },
        { title: 'Indexing & tools', url: '/cms/badges' },
        { title: 'Menu', url: '/cms/menu' },
        { title: 'Pages', url: '/cms/pages' },
        { title: 'Editorial board', url: '/cms/editorial-board' },
        { title: 'Footer & social', url: '/cms/footer' },
    ],
};

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const mainNavItems: NavItem[] = auth.user?.is_site_admin
        ? [dashboardNavItem, journalsNavItem, aiNavItem, indexingNavItem, notificationsNavItem, storageNavItem, cmsNavItem]
        : [dashboardNavItem];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
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
                {/*
                  Outside the site-admin gate above on purpose: authors, reviewers and editors
                  see only "Dashboard" in the main nav, and they are most of who the handbook is
                  written for. Gating it would hide it from exactly the people who need it.

                  A plain anchor to a new tab rather than an Inertia Link: the handbook is read
                  *while* doing something — writing a decision, filling in a submission — so
                  navigating in place would throw that half-finished work away. It is also a
                  public page, so it leaves the staff layout entirely.
                */}
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Handbook">
                            <a href="/handbook" target="_blank" rel="noreferrer">
                                <BookOpen />
                                <span>Handbook</span>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
