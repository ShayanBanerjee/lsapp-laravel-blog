import { LucideIcon } from 'lucide-react';
import type { PersonaSummary, Universe, UniversePreview } from './universe';

export * from './universe';

export interface Auth {
    user: User | null;
    personas: PersonaSummary[];
    activePersonaId: number | null;
    canCreatePersona: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface ReadingPreferences {
    font: string;
    size: number;
    leading: number;
    measure: number;
    /** Resolved server-side from an allowlist. */
    stack: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    /** Default theme for the request; pages may override with their own `universe` prop. */
    activeUniverse: Universe | null;
    universeIndex: UniversePreview[];
    reading: ReadingPreferences;
    socialProviders: string[];
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_premium: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
