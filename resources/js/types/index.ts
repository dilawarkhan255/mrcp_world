import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    canManageContent: boolean;
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface Exam {
    id: number;
    name: string;
    slug: string;
    /** Name of the Qbank the exam belongs to, used to group the switcher. */
    qbank?: string | null;
}
/** Community and app-store links configured in config/services.php. */
export interface CommunityLinks {
    whatsapp: string | null;
    play_store: string | null;
}
/** A node of the student sidebar, as defined in config/student.php. */
export interface NavNode {
    title: string;
    icon?: string;
    href?: string | null;
    /** Extra paths that keep this node active. */
    match?: string[];
    children?: NavNode[];
    /** Needs a subscription the student does not have. */
    locked?: boolean;
    /** A heading in the sidebar (for example "More"), not a link. */
    section?: string;
}
/** Subscription state of the selected exam's Qbank. */
export interface AccessState {
    has_access: boolean;
    qbank: string | null;
}
export interface SharedData {
    [key: string]: unknown;
    exams: Exam[];
    currentExam: Exam | null;
    navigation: NavNode[];
    links?: CommunityLinks;
    access?: AccessState | null;
    flash?: { status: string | null };
    /** The AI tutor (Claude) is on when an API key is configured. */
    ai?: { enabled: boolean };
    /** Unread notifications for the bell of the student app. */
    notifications?: { unread: number } | null;
    /** Copy protection of the student app; watermark is the signed-in student's email and the date. */
    protection?: { enabled: boolean; watermark: string | null };
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    leaderboard_opt_in?: boolean;
    leaderboard_name?: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
