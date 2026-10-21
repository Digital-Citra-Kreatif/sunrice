import {
    Blocks, BookOpen, Bot, Briefcase, Calendar, ChartColumn, Circle, ClipboardList, CreditCard, Database, ExternalLink, FileText,
    Folder, Globe, History, Home, Image, Inbox, LayoutGrid, LayoutTemplate, Library, LifeBuoy, Link, ListTree, Mail, MapPin, Megaphone,
    MessageSquare, Newspaper, Package, Receipt, Search, Settings, Shield, ShoppingBag, ShoppingCart, Star, Tag, Tags, Truck, Users, Wrench,
    type LucideIcon,
} from 'lucide-react';

/**
 * Icons navigation items can name (kebab-case lucide names). Unknown names
 * fall back to a dot, so the bundle only ships the icons listed here.
 */
const icons: Record<string, LucideIcon> = {
    blocks: Blocks, 'book-open': BookOpen, bot: Bot, briefcase: Briefcase, calendar: Calendar, 'chart-column': ChartColumn,
    'clipboard-list': ClipboardList, 'credit-card': CreditCard, database: Database, 'external-link': ExternalLink,
    'file-text': FileText, folder: Folder, globe: Globe, history: History, home: Home, image: Image, inbox: Inbox,
    'layout-grid': LayoutGrid, 'layout-template': LayoutTemplate, library: Library, 'life-buoy': LifeBuoy, link: Link,
    'list-tree': ListTree, mail: Mail, 'map-pin': MapPin, megaphone: Megaphone, 'message-square': MessageSquare,
    newspaper: Newspaper, package: Package, receipt: Receipt, search: Search, settings: Settings, shield: Shield, 'shopping-bag': ShoppingBag,
    'shopping-cart': ShoppingCart, star: Star, tag: Tag, tags: Tags, truck: Truck, users: Users, wrench: Wrench,
};

export function navIcon(name?: string): LucideIcon {
    return (name && icons[name]) || Circle;
}
