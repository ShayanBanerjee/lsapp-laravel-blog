import { Head } from '@inertiajs/react';

export interface SeoPayload {
    title: string;
    description: string;
    canonical: string;
    image: string | null;
    type: string;
    publishedAt: string | null;
    modifiedAt: string | null;
    author: string | null;
    section: string | null;
    readingTime: number;
    jsonLd: Record<string, unknown>;
}

/**
 * Search and social metadata for a page.
 *
 * The JSON-LD block is the part that earns a rich result — Open Graph tags
 * only control how a link unfurls in a chat app. Both matter, for different
 * reasons, so both are emitted.
 */
export function SeoHead({ seo }: { seo: SeoPayload }) {
    return (
        <Head title={seo.title}>
            <meta name="description" content={seo.description} head-key="description" />
            <link rel="canonical" href={seo.canonical} head-key="canonical" />

            <meta property="og:type" content={seo.type} head-key="ogtype" />
            <meta property="og:title" content={seo.title} head-key="ogtitle" />
            <meta property="og:description" content={seo.description} head-key="ogdesc" />
            <meta property="og:url" content={seo.canonical} head-key="ogurl" />
            {seo.image && <meta property="og:image" content={seo.image} head-key="ogimage" />}

            <meta name="twitter:card" content={seo.image ? 'summary_large_image' : 'summary'} head-key="twcard" />
            <meta name="twitter:title" content={seo.title} head-key="twtitle" />
            <meta name="twitter:description" content={seo.description} head-key="twdesc" />
            {seo.image && <meta name="twitter:image" content={seo.image} head-key="twimage" />}

            {seo.publishedAt && <meta property="article:published_time" content={seo.publishedAt} head-key="pubtime" />}
            {seo.modifiedAt && <meta property="article:modified_time" content={seo.modifiedAt} head-key="modtime" />}
            {seo.author && <meta property="article:author" content={seo.author} head-key="author" />}
            {seo.section && <meta property="article:section" content={seo.section} head-key="section" />}

            <script type="application/ld+json" head-key="jsonld">
                {JSON.stringify(seo.jsonLd)}
            </script>
        </Head>
    );
}
