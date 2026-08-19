<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Circle;
use App\Models\Post;
use App\Models\Universe;
use Illuminate\Http\Response;

/**
 * Machine-readable surfaces: sitemap, robots, RSS.
 *
 * All three are cached — they are hit by crawlers far more often than they
 * change, and regenerating them per request is free money for a scraper.
 */
class FeedController extends Controller
{
    public function sitemap(): Response
    {
        $xml = cache()->remember('sitemap.xml', now()->addHour(), function () {
            $urls = collect();

            $urls->push(['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily']);
            foreach (['/posts', '/universes', '/circles', '/categories', '/deep-field'] as $path) {
                $urls->push(['loc' => url($path), 'priority' => '0.8', 'changefreq' => 'daily']);
            }

            Post::published()->latest('published_at')->limit(5000)->get(['slug', 'updated_at'])
                ->each(fn (Post $post) => $urls->push([
                    'loc' => url("/posts/{$post->slug}"),
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'priority' => '0.9',
                    'changefreq' => 'weekly',
                ]));

            Universe::all(['slug'])->each(fn ($u) => $urls->push(['loc' => url("/universes/{$u->slug}"), 'priority' => '0.7']));
            Category::all(['slug'])->each(fn ($c) => $urls->push(['loc' => url("/categories/{$c->slug}"), 'priority' => '0.7']));
            Circle::all(['slug'])->each(fn ($c) => $urls->push(['loc' => url("/circles/{$c->slug}"), 'priority' => '0.6']));

            $body = $urls->map(function (array $url) {
                $parts = '<loc>'.e($url['loc']).'</loc>';
                if (! empty($url['lastmod'])) {
                    $parts .= '<lastmod>'.e($url['lastmod']).'</lastmod>';
                }
                if (! empty($url['changefreq'])) {
                    $parts .= '<changefreq>'.e($url['changefreq']).'</changefreq>';
                }
                $parts .= '<priority>'.e($url['priority'] ?? '0.5').'</priority>';

                return "<url>{$parts}</url>";
            })->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            // Private surfaces: no value to a crawler, and some are per-user.
            'Disallow: /dashboard',
            'Disallow: /library',
            'Disallow: /letters',
            'Disallow: /settings',
            'Disallow: /write',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }

    public function rss(): Response
    {
        $xml = cache()->remember('feed.rss', now()->addMinutes(30), function () {
            $items = Post::published()
                ->with(['persona', 'universe'])
                ->latest('published_at')
                ->limit(50)
                ->get()
                ->map(function (Post $post) {
                    $link = url("/posts/{$post->slug}");

                    return '<item>'
                        .'<title>'.e($post->title).'</title>'
                        .'<link>'.e($link).'</link>'
                        .'<guid isPermaLink="true">'.e($link).'</guid>'
                        .'<pubDate>'.e($post->published_at?->toRfc2822String() ?? '').'</pubDate>'
                        .'<description>'.e($post->excerpt ?? '').'</description>'
                        .'<dc:creator>'.e($post->persona?->display_name ?? 'Anonymous').'</dc:creator>'
                        .'</item>';
                })->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?>'
                .'<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">'
                .'<channel>'
                .'<title>'.e(config('app.name')).'</title>'
                .'<link>'.e(url('/')).'</link>'
                .'<description>Writing from every world on Inkfathom.</description>'
                .'<language>en</language>'
                .'<atom:link href="'.e(url('/feed.xml')).'" rel="self" type="application/rss+xml"/>'
                .$items
                .'</channel></rss>';
        });

        return response($xml, 200, ['Content-Type' => 'application/rss+xml']);
    }
}
