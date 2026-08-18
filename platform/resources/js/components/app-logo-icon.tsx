import { SVGAttributes } from 'react';

/**
 * The Inkfathom mark, as a single-colour fill.
 *
 * Kept at this path and API on purpose: the auth layouts and the sidebar all
 * render it with `fill-current`, so replacing the artwork here updates every
 * surface at once. Everything is filled (no strokes) so `fill-current` colours
 * the whole mark, including the depth strata.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            {/* The drop */}
            <path d="M16 2.5C16 2.5 9.5 10.6 9.5 14.2a6.5 6.5 0 1 0 13 0C22.5 10.6 16 2.5 16 2.5Z" />
            {/* Depth strata, narrowing as they descend */}
            <rect x="6" y="23" width="20" height="2" rx="1" opacity="0.85" />
            <rect x="9.5" y="26.6" width="13" height="2" rx="1" opacity="0.55" />
            <rect x="13" y="29.6" width="6" height="2" rx="1" opacity="0.3" />
        </svg>
    );
}
