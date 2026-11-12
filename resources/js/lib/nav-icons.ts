import {
    BookOpen,
    Bookmark,
    ClipboardList,
    CreditCard,
    FileClock,
    Folder,
    GraduationCap,
    History,
    Images,
    Layers,
    LayoutGrid,
    Library,
    ListChecks,
    MessageSquare,
    RotateCcw,
    Search,
    Sparkles,
    TrendingUp,
    Trophy,
    Zap,
    type LucideIcon,
} from 'lucide-vue-next';

// Only the icon names used in config/student.php and App\Support\StudentNavigation are listed,
// so the other icons are tree-shaken. A name missing here shows no icon.
const icons: Record<string, LucideIcon> = {
    BookOpen,
    Bookmark,
    ClipboardList,
    CreditCard,
    FileClock,
    Folder,
    GraduationCap,
    History,
    Images,
    Layers,
    LayoutGrid,
    Library,
    ListChecks,
    MessageSquare,
    RotateCcw,
    Search,
    Sparkles,
    TrendingUp,
    Trophy,
    Zap,
};

export function navIcon(name: string | undefined): LucideIcon | undefined {
    return name ? icons[name] : undefined;
}
