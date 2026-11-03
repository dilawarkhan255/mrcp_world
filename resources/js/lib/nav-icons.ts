import {
    BookOpen,
    Bookmark,
    ClipboardList,
    CreditCard,
    LayoutGrid,
    Library,
    ListChecks,
    MessageSquare,
    RotateCcw,
    Search,
    TrendingUp,
    type LucideIcon,
} from 'lucide-vue-next';

// Only the icon names used in config/student.php are listed, so the other icons are tree-shaken.
const icons: Record<string, LucideIcon> = {
    BookOpen,
    Bookmark,
    ClipboardList,
    CreditCard,
    LayoutGrid,
    Library,
    ListChecks,
    MessageSquare,
    RotateCcw,
    Search,
    TrendingUp,
};

export function navIcon(name: string | undefined): LucideIcon | undefined {
    return name ? icons[name] : undefined;
}
