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
}
/** A node of the student sidebar, as defined in config/student.php. */
export interface NavNode {
    title: string;
    icon?: string;
    href?: string | null;
    /** Extra paths that keep this node active. */
    match?: string[];
    children?: NavNode[];
}
export interface SharedData {
    [key: string]: unknown;
    exams: Exam[];
    currentExam: Exam | null;
    navigation: NavNode[];
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
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
