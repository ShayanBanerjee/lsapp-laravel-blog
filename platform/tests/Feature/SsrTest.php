<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for server-side rendering.
 *
 * Every failure mode here is silent. When the SSR process throws, Inertia
 * catches it and falls back to client rendering, so the page still returns 200
 * and still looks perfect in a browser — while every social scraper and any
 * crawler that does not execute JavaScript sees an empty shell. None of these
 * assertions would be missed by looking at the site.
 */
class SsrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private function publishedPost(): Post
    {
        $author = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'writer'.$author->id,
            'display_name' => 'Long Light',
        ]);

        return Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-published-piece',
            'title' => 'A Published Piece',
            'body' => '<p>The first paragraph.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * The SSR process is a Node runtime with no `route()` global. Without this
     * prop every page that calls route() throws server-side and silently
     * degrades to client rendering.
     */
    public function test_the_route_table_is_shared_for_the_ssr_process(): void
    {
        $this->get('/')->assertInertia(
            fn ($page) => $page->has('ziggy.routes.home')->has('ziggy.url')->has('ziggy.location')
        );
    }

    /**
     * Inertia navigations render in the browser, where the route table is
     * already a global. Re-sending it would add several KB to every navigation
     * and buy nothing.
     */
    public function test_the_route_table_is_not_re_sent_on_inertia_navigations(): void
    {
        $version = app(HandleInertiaRequests::class)->version(request());

        // An X-Inertia request returns JSON, not a view, so assertInertia()
        // (which reads view data) cannot see it.
        $response = $this->get('/', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ])->assertOk();

        $this->assertNull($response->json('props.ziggy'));
    }

    /**
     * Exactly one of each. The root template renders these server-side so they
     * survive SSR being down; if @inertiaHead were reinstated each page would
     * carry a second, competing copy from its own <Head>.
     */
    public function test_a_page_renders_exactly_one_title_and_description(): void
    {
        $html = $this->get('/posts/'.$this->publishedPost()->slug)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<title'), 'expected exactly one <title>');
        $this->assertSame(1, substr_count($html, '<meta name="description"'), 'expected exactly one description');
    }

    /**
     * The shareable surfaces each need their own metadata. Anything Inertia's
     * <Head> injects is invisible to Facebook, LinkedIn, WhatsApp and Slack,
     * none of which run JavaScript — so this has to come from the controller.
     */
    public function test_every_shareable_page_carries_its_own_server_rendered_metadata(): void
    {
        $pages = [
            '/' => 'Write in six worlds',
            '/posts' => 'Read',
            '/circles' => 'Circles',
            '/categories' => 'Browse by subject',
            '/deep-field' => 'The Deep Field',
            '/universes/cosmos' => 'Cosmos',
            '/posts/'.$this->publishedPost()->slug => 'A Published Piece',
        ];

        foreach ($pages as $path => $title) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString("<title inertia>{$title}</title>", $html, "{$path} title");
            $this->assertStringContainsString('<meta property="og:title" content="'.e($title).'"', $html, "{$path} og:title");
            $this->assertStringContainsString('<link rel="canonical"', $html, "{$path} canonical");
        }
    }
}
