/**
 * The links of the public site, used by the top bar and the footer, so
 * every page links to the same places. Section links start with "/" so
 * they also work from the legal pages and the exam demos.
 */
export interface PublicLink {
    label: string;
    href: string;
}

export const sectionLinks: PublicLink[] = [
    { label: 'Exams', href: '/#exams' },
    { label: 'Features', href: '/#features' },
    { label: 'Pricing', href: '/#pricing' },
];

export const accountLinks: PublicLink[] = [
    { label: 'Log in', href: '/login' },
    { label: 'Subscribe', href: '/#pricing' },
];

export const legalLinks: PublicLink[] = [
    { label: 'Terms of use', href: '/terms' },
    { label: 'Privacy notice', href: '/privacy' },
    { label: 'Contact', href: '/contact' },
    { label: 'Cookie settings', href: '/cookie-settings' },
];
