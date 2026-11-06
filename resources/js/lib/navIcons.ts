import {
    Bot,
    Building2,
    Cpu,
    FolderKanban,
    Inbox,
    LayoutGrid,
    Layers,
    Newspaper,
    ScrollText,
    Settings,
    Shield,
    SlidersHorizontal,
    Trophy,
    UserCog,
    Users,
    Wallet,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

/**
 * Icons the server may name in the shared "nav" prop (Navigation.php).
 */
const icons: Record<string, LucideIcon> = {
    'layout-grid': LayoutGrid,
    users: Users,
    'folder-kanban': FolderKanban,
    bot: Bot,
    cpu: Cpu,
    trophy: Trophy,
    wallet: Wallet,
    settings: Settings,
    newspaper: Newspaper,
    inbox: Inbox,
    shield: Shield,
    'building-2': Building2,
    layers: Layers,
    'user-cog': UserCog,
    'scroll-text': ScrollText,
    'sliders-horizontal': SlidersHorizontal,
};

export function navIcon(name: string): LucideIcon {
    return icons[name] ?? LayoutGrid;
}
