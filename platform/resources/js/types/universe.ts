/**
 * Token contract shared with the server (see App\Models\Universe::tokens).
 * Values live in the database so adding a universe never touches the frontend.
 */
export interface UniverseTheme {
    bg: string;
    bgDeep: string;
    surface1: string;
    surface2: string;
    border: string;
    text: string;
    textMuted: string;
    accent: string;
    accentFg: string;
    accentSoft: string;
    metalBase: string;
    metalSheen: string;
    metalEdge: string;
    metalShadow: string;
    grainAngle: string;
    glow: string;
    halo: string;
    scheme: 'light' | 'dark';
}

/** Always safe to ship: identity plus a three-colour preview. */
export interface UniversePreview {
    /** Present only where a form needs to post it (see PersonaController). */
    id?: number;
    slug: string;
    name: string;
    tagline: string;
    description: string;
    material: string;
    is_premium: boolean;
    scheme: 'light' | 'dark';
    /** Bundled photography under public/images. Shipped even for locked worlds. */
    hero_image: string;
    hero_credit: { name: string; username: string } | null;
    swatch: { bg: string; accent: string; metalBase: string };
    locked?: boolean;
    posts_count?: number;
    writers_count?: number;
}

/** Full token set — only present when the viewer is entitled to it. */
export interface Universe extends UniversePreview {
    theme: UniverseTheme;
}

export interface PersonaSummary {
    id: number;
    handle: string;
    display_name: string;
    avatar_path?: string | null;
    universe: UniversePreview;
}

export interface PostCard {
    id?: number;
    slug: string;
    title: string;
    excerpt: string | null;
    cover_url: string | null;
    status?: 'draft' | 'published';
    reading_time: number;
    published_at?: string | null;
    published_human: string | null;
    updated_human?: string | null;
    persona: { handle: string; display_name: string; avatar_path?: string | null } | null;
    universe: UniversePreview | null;
}
