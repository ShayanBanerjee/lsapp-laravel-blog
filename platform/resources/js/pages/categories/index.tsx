import { Panel } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';
import * as Icons from 'lucide-react';
import { ArrowUpRight } from 'lucide-react';

interface CategoryRow {
    slug: string;
    name: string;
    description: string | null;
    icon: string | null;
    posts_count: number | null;
}

/** Icon name comes from the database, so fall back rather than crash. */
function CategoryIcon({ name, className }: { name: string | null; className?: string }) {
    const Icon = (name && (Icons as unknown as Record<string, Icons.LucideIcon>)[name]) || Icons.Hash;

    return <Icon className={className} />;
}

export default function CategoriesIndex({ categories }: { categories: CategoryRow[] }) {
    return (
        <SiteLayout wide>
            <Head title="Categories" />

            <header className="mb-12 max-w-3xl">
                <h1 className="font-display text-4xl sm:text-6xl">Browse by subject</h1>
                <p className="mt-5 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    A universe is how a piece <em>feels</em>. A category is what it is <em>about</em>. They are kept separate on purpose, so a
                    technical essay can still be written in the dark.
                </p>
            </header>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {categories.map((category, index) => (
                    <Reveal key={category.slug} delay={Math.min(index * 55, 330)}>
                        <Link href={`/categories/${category.slug}`} className="group block h-full">
                            <Panel interactive className="flex h-full flex-col p-6">
                                <div className="mb-4 flex items-center gap-3">
                                    <span
                                        className="inline-flex size-10 items-center justify-center rounded-[10px]"
                                        style={{ backgroundColor: 'var(--u-accent-soft)', color: 'var(--u-accent)' }}
                                    >
                                        <CategoryIcon name={category.icon} className="size-5" />
                                    </span>
                                    <h2 className="font-display text-xl">{category.name}</h2>
                                    <ArrowUpRight
                                        className="ml-auto size-4 shrink-0 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                        style={{ color: 'var(--u-accent)' }}
                                    />
                                </div>
                                {category.description && (
                                    <p className="text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                        {category.description}
                                    </p>
                                )}
                                <p className="mt-4 text-[11px] tracking-wide uppercase" style={{ color: 'var(--u-text-muted)' }}>
                                    {category.posts_count ?? 0} {category.posts_count === 1 ? 'piece' : 'pieces'}
                                </p>
                            </Panel>
                        </Link>
                    </Reveal>
                ))}
            </div>
        </SiteLayout>
    );
}
