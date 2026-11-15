import {
    BadgeCheck,
    Banknote,
    Bell,
    BookOpen,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    ChartNoAxesCombined,
    ClipboardList,
    CreditCard,
    Crown,
    FileText,
    Gauge,
    GraduationCap,
    Images,
    LayoutDashboard,
    ListChecks,
    MapPin,
    Megaphone,
    Package,
    PanelsTopLeft,
    Settings2,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Stethoscope,
    Store,
    Tags,
    Truck,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { NavItem } from '@/types';
import { commerceNoticeCopy } from '@/lib/commerceNoticeCopy';
import { contentCopy } from '@/lib/contentCopy';
import { paymentSettingsCopy } from '@/lib/paymentSettingsCopy';
import { reportCopy } from '@/lib/reportCopy';
import { websiteCopy } from '@/lib/websiteCopy';
import { toUrl } from '@/lib/utils';

export type SidebarNavGroup = {
    id: string;
    title: string;
    icon: LucideIcon;
    items: NavItem[];
};

export function accountNavigation(isVip: boolean): NavItem[] {
    return [
        { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
        { title: 'My orders', href: '/orders', icon: ShoppingBag },
        { title: 'My membership', href: '/membership', icon: ShieldCheck },
        { title: 'My Programs', href: '/my-programs', icon: BookOpen },
        {
            title: 'My Appointments',
            href: '/appointments',
            icon: CalendarCheck,
        },
        ...(isVip
            ? [
                  {
                      title: 'Book Appointment',
                      href: '/vip/appointments/book',
                      icon: CalendarPlus,
                  },
                  {
                      title: 'VIP Programs',
                      href: '/vip/programs',
                      icon: GraduationCap,
                  },
              ]
            : []),
        { title: 'Notifications', href: '/notifications', icon: Bell },
        { title: 'Shopping cart', href: '/cart', icon: ShoppingCart },
        { title: 'My addresses', href: '/addresses', icon: MapPin },
    ];
}

export const adminNavigation: NavItem[] = [
    { title: 'Administration', href: '/admin/dashboard', icon: Gauge },
    {
        title: reportCopy.title,
        href: '/admin/reports',
        icon: ChartNoAxesCombined,
    },
    { title: 'Customers', href: '/admin/customers', icon: UsersRound },
];

export function adminNavigationGroups(isVip: boolean): SidebarNavGroup[] {
    return [
        {
            id: 'commerce',
            title: 'Store & payments',
            icon: Store,
            items: [
                {
                    title: 'Manage Orders',
                    href: '/admin/orders',
                    icon: ShoppingBag,
                },
                { title: 'Products', href: '/admin/products', icon: Package },
                { title: 'Categories', href: '/admin/categories', icon: Tags },
                { title: 'Payments', href: '/admin/payments', icon: Banknote },
                {
                    title: 'Shipping settings',
                    href: '/admin/shipping',
                    icon: Truck,
                },
                {
                    title: paymentSettingsCopy.title,
                    href: '/admin/payment-settings',
                    icon: CreditCard,
                },
                {
                    title: commerceNoticeCopy.title,
                    href: '/admin/commerce-notices',
                    icon: Megaphone,
                },
            ],
        },
        {
            id: 'vip',
            title: 'VIP & programs',
            icon: Crown,
            items: [
                {
                    title: 'VIP memberships',
                    href: '/admin/memberships',
                    icon: BadgeCheck,
                },
                {
                    title: 'Membership plans',
                    href: '/admin/membership-plans',
                    icon: ListChecks,
                },
                {
                    title: 'Manage VIP Programs',
                    href: '/admin/programs',
                    icon: GraduationCap,
                },
            ],
        },
        {
            id: 'appointments',
            title: 'Appointments',
            icon: CalendarClock,
            items: [
                {
                    title: 'Appointment Requests',
                    href: '/admin/appointments',
                    icon: ClipboardList,
                },
                {
                    title: 'Appointment Calendar',
                    href: '/admin/appointments/calendar',
                    icon: CalendarDays,
                },
                {
                    title: 'Appointment services',
                    href: '/admin/appointment-services',
                    icon: Stethoscope,
                },
            ],
        },
        {
            id: 'website',
            title: 'Website',
            icon: PanelsTopLeft,
            items: [
                {
                    title: websiteCopy.settings,
                    href: '/admin/website-settings',
                    icon: Settings2,
                },
                {
                    title: websiteCopy.banners,
                    href: '/admin/banners',
                    icon: Images,
                },
                {
                    title: contentCopy.title,
                    href: '/admin/content-pages',
                    icon: FileText,
                },
            ],
        },
        {
            id: 'account',
            title: 'My account',
            icon: UserRound,
            items: accountNavigation(isVip),
        },
    ];
}

// Longest matching destination wins, so the calendar never highlights requests.
export function activeNavigationHref(
    items: NavItem[],
    url: string,
): string | undefined {
    const path = url.split(/[?#]/)[0];
    return items
        .map((item) => toUrl(item.href))
        .filter((href) => path === href || path.startsWith(`${href}/`))
        .sort((a, b) => b.length - a.length)[0];
}
