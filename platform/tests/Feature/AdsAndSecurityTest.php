<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdsAndSecurityTest extends TestCase
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
            'display_name' => 'Writer',
        ]);

        return Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-piece',
            'title' => 'A Piece',
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /* -------------------- ads -------------------- */

    public function test_free_readers_are_served_ads(): void
    {
        $post = $this->publishedPost();

        $ads = $this->get("/posts/{$post->slug}")->viewData('page')['props']['ads'];

        $this->assertNotEmpty($ads);
    }

    /**
     * Paying must actually remove the ad, not hide it. If the creative were
     * still serialized into the page the customer has not received the thing
     * they bought — and it would be trivially un-hidden.
     */
    public function test_premium_readers_receive_no_ad_payload_at_all(): void
    {
        $post = $this->publishedPost();
        $premium = User::factory()->create(['is_premium' => true]);

        $props = $this->actingAs($premium)->get("/posts/{$post->slug}")->viewData('page')['props'];

        $this->assertSame([], $props['ads']);

        // Belt and braces: the creative's copy must not appear anywhere in the
        // rendered response either.
        $this->actingAs($premium)->get("/posts/{$post->slug}")
            ->assertDontSee('Read without interruption');
    }

    public function test_the_feed_serves_ads_to_free_users_and_none_to_premium(): void
    {
        $this->publishedPost();

        $this->assertNotEmpty($this->get('/posts')->viewData('page')['props']['ads']);

        $premium = User::factory()->create(['is_premium' => true]);
        $this->assertSame([], $this->actingAs($premium)->get('/posts')->viewData('page')['props']['ads']);
    }

    /* -------------------- security headers -------------------- */

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
    }

    /**
     * The CSP is the second line of defence behind HtmlSanitizer for post
     * bodies, which are author HTML rendered with dangerouslySetInnerHTML.
     */
    public function test_the_csp_blocks_object_and_frame_embedding(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    /**
     * Regression: the CSP must not block Vite's dev server.
     *
     * Vite serves dev assets from a *different origin* (its own port), which
     * 'self' does not cover. Getting this wrong renders the whole app blank in
     * development while every server-side test still passes, because tests run
     * against built assets.
     */
    public function test_the_csp_allows_the_vite_dev_origin_when_running_hot(): void
    {
        $hotFile = public_path('hot');
        $existed = is_file($hotFile);
        $original = $existed ? file_get_contents($hotFile) : null;

        file_put_contents($hotFile, 'http://127.0.0.1:5173');

        // The dev origin is only ever added in the local environment; tests
        // otherwise run as `testing` and would see the strict production policy.
        $this->app->detectEnvironment(fn () => 'local');

        try {
            $csp = $this->get('/')->headers->get('Content-Security-Policy');

            $this->assertStringContainsString('http://127.0.0.1:5173', $csp);
            // HMR needs a websocket back to the same origin.
            $this->assertStringContainsString('ws://127.0.0.1:5173', $csp);
        } finally {
            $existed ? file_put_contents($hotFile, $original) : @unlink($hotFile);
        }
    }

    /** With no hot file (production / built assets) the policy stays strict. */
    public function test_the_csp_stays_strict_without_a_vite_dev_server(): void
    {
        $hotFile = public_path('hot');
        $existed = is_file($hotFile);
        $original = $existed ? file_get_contents($hotFile) : null;

        if ($existed) {
            unlink($hotFile);
        }

        $this->app->detectEnvironment(fn () => 'local');

        try {
            $csp = $this->get('/')->headers->get('Content-Security-Policy');

            $this->assertStringNotContainsString('5173', $csp);
        } finally {
            if ($existed) {
                file_put_contents($hotFile, $original);
            }
        }
    }

    /* -------------------- rate limiting -------------------- */

    public function test_prose_endpoints_are_rate_limited(): void
    {
        $post = $this->publishedPost();
        $reader = User::factory()->create();

        // The prose limiter allows 12/minute; the 13th must be refused.
        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($reader)
                ->post("/posts/{$post->slug}/responses", ['body' => "Response number {$i}."])
                ->assertRedirect();
        }

        $this->actingAs($reader)
            ->post("/posts/{$post->slug}/responses", ['body' => 'One too many.'])
            ->assertStatus(429);
    }

    public function test_marking_is_limited_far_more_generously_than_prose(): void
    {
        $post = $this->publishedPost();
        $reader = User::factory()->create();

        // 20 marks in a sitting is normal reading behaviour and must not trip.
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($reader)->post("/posts/{$post->slug}/highlights", [
                'block_index' => 0, 'start_offset' => $i, 'end_offset' => $i + 3, 'quote' => 'abc',
            ])->assertRedirect();
        }
    }
}
