<?php

namespace Tests\Feature;

use App\Models\Highlight;
use App\Models\Integration as Connection;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\Integrations\IntegrationRegistry;
use App\Support\Integrations\Providers\Ghost;
use App\Support\Integrations\PublishesPosts;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{0: User, 1: Post} */
    private function published(): array
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'ada',
            'display_name' => 'Ada Lovelace',
        ]);

        $post = Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'on-the-engine',
            'title' => 'On the Engine',
            'excerpt' => 'A note.',
            'body' => '<h2>The engine</h2><p>It weaves algebraic patterns.</p>',
            'status' => 'published',
            'reading_time' => 2,
            'published_at' => now()->subDay(),
        ]);

        return [$user, $post];
    }

    private function connect(User $user, string $provider, array $credentials): Connection
    {
        return $user->integrations()->create([
            'provider' => $provider,
            'credentials' => $credentials,
            'verified_at' => now(),
        ]);
    }

    public function test_the_connections_page_renders_the_whole_catalogue(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/integrations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/integrations')
                ->has('catalogue', 10)
                ->has('obsidian.blurb')
                ->has('connections'));
    }

    public function test_every_registered_provider_declares_a_complete_contract(): void
    {
        $registry = new IntegrationRegistry;

        $this->assertCount(10, $registry->all());

        $registry->all()->each(function ($provider) {
            $this->assertNotSame('', $provider->key());
            $this->assertNotSame('', $provider->label());
            $this->assertNotSame('', $provider->blurb());
            $this->assertNotEmpty($provider->credentialFields(), $provider->key().' declares no credentials');

            foreach ($provider->credentialFields() as $field) {
                $this->assertArrayHasKey('key', $field);
                $this->assertArrayHasKey('label', $field);
                $this->assertArrayHasKey('help', $field);
                $this->assertArrayHasKey('secret', $field);
            }
        });
    }

    public function test_credentials_are_verified_before_they_are_stored(): void
    {
        $user = User::factory()->create();

        Http::fake(['readwise.io/api/v2/auth/' => Http::response([], 401)]);

        $this->actingAs($user)
            ->post('/settings/integrations/readwise', ['credentials' => ['token' => 'wrong']])
            ->assertSessionHasErrors('credentials');

        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_verified_credential_is_stored_encrypted(): void
    {
        $user = User::factory()->create();

        Http::fake(['readwise.io/api/v2/auth/' => Http::response([], 204)]);

        $this->actingAs($user)
            ->post('/settings/integrations/readwise', ['credentials' => ['token' => 'secret-token']])
            ->assertSessionHas('success');

        $connection = Connection::firstOrFail();
        $this->assertSame('secret-token', $connection->credentials['token']);

        // The stored column must not be the plaintext.
        $raw = DB::table('integrations')->value('credentials');
        $this->assertStringNotContainsString('secret-token', $raw);
    }

    public function test_credentials_never_reach_the_browser(): void
    {
        $user = User::factory()->create();
        $this->connect($user, 'readwise', ['token' => 'secret-token']);

        $response = $this->actingAs($user)->get('/settings/integrations');

        $response->assertOk();
        $this->assertStringNotContainsString('secret-token', $response->getContent());
        $response->assertInertia(fn ($page) => $page->has('connections.readwise')
            ->missing('connections.readwise.credentials'));
    }

    public function test_undeclared_credential_fields_are_discarded(): void
    {
        $user = User::factory()->create();

        Http::fake(['readwise.io/api/v2/auth/' => Http::response([], 204)]);

        $this->actingAs($user)->post('/settings/integrations/readwise', [
            'credentials' => ['token' => 'good', 'smuggled' => 'value'],
        ]);

        $this->assertSame(['token'], array_keys(Connection::firstOrFail()->credentials));
    }

    public function test_an_unknown_provider_is_a_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/integrations/myspace', ['credentials' => []])
            ->assertNotFound();
    }

    public function test_cross_posting_creates_a_draft_with_a_canonical_url(): void
    {
        [$user, $post] = $this->published();
        $this->connect($user, 'devto', ['api_key' => 'key']);

        Http::fake(['dev.to/api/articles' => Http::response(['url' => 'https://dev.to/ada/on-the-engine'])]);

        $this->actingAs($user)
            ->post("/posts/{$post->slug}/share/devto")
            ->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $article = $request->data()['article'];

            // A cross-post that publishes immediately, or that claims to be
            // canonical, is the failure mode this asserts against.
            return $article['published'] === false
                && str_contains($article['canonical_url'], 'on-the-engine');
        });
    }

    public function test_cross_posting_requires_the_service_to_be_connected(): void
    {
        [$user, $post] = $this->published();

        Http::fake();

        $this->actingAs($user)->post("/posts/{$post->slug}/share/devto")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_only_the_author_can_cross_post_their_piece(): void
    {
        [, $post] = $this->published();
        $stranger = User::factory()->create();
        $this->connect($stranger, 'devto', ['api_key' => 'key']);

        Http::fake();

        $this->actingAs($stranger)->post("/posts/{$post->slug}/share/devto")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_a_failure_at_the_far_end_is_reported_not_swallowed(): void
    {
        [$user, $post] = $this->published();
        $this->connect($user, 'devto', ['api_key' => 'key']);

        Http::fake(['dev.to/api/articles' => Http::response(['error' => 'title is too long'], 422)]);

        $this->actingAs($user)
            ->post("/posts/{$post->slug}/share/devto")
            ->assertSessionHas('error');
    }

    public function test_marks_are_sent_to_readwise_and_scoped_to_their_owner(): void
    {
        [$author, $post] = $this->published();
        $reader = User::factory()->create();
        $other = User::factory()->create();

        Highlight::create([
            'post_id' => $post->id, 'user_id' => $reader->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 10, 'quote' => 'Mine to keep',
        ]);
        Highlight::create([
            'post_id' => $post->id, 'user_id' => $other->id,
            'block_index' => 0, 'start_offset' => 11, 'end_offset' => 20, 'quote' => 'Somebody elses',
        ]);

        $this->connect($reader, 'readwise', ['token' => 'token']);

        Http::fake(['readwise.io/api/v2/highlights/' => Http::response([], 200)]);

        $this->actingAs($reader)->post('/integrations/readwise/highlights')->assertSessionHas('success');

        Http::assertSent(function ($request) {
            $texts = array_column($request->data()['highlights'], 'text');

            // A reading history is private. Sending another reader's marks
            // would be a data leak dressed as a feature.
            return in_array('Mine to keep', $texts, true) && ! in_array('Somebody elses', $texts, true);
        });
    }

    public function test_sending_marks_requires_a_service_that_takes_them(): void
    {
        $user = User::factory()->create();
        $this->connect($user, 'devto', ['api_key' => 'key']);

        Http::fake();

        $this->actingAs($user)->post('/integrations/devto/highlights')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_disconnecting_deletes_the_stored_credential(): void
    {
        $user = User::factory()->create();
        $this->connect($user, 'readwise', ['token' => 'secret-token']);

        $this->actingAs($user)->delete('/settings/integrations/readwise')->assertRedirect();

        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_ghost_admin_token_is_a_signed_jwt_for_the_admin_audience(): void
    {
        $ghost = new Ghost;

        Http::fake(['*/ghost/api/admin/site/' => Http::response(['site' => ['title' => 'Blog']])]);

        $ghost->verify(['site_url' => 'https://blog.example', 'admin_key' => '640b...:'.bin2hex('supersecret')]);

        Http::assertSent(function ($request) {
            $token = str_replace('Ghost ', '', $request->header('Authorization')[0]);
            [$header, $payload, $signature] = explode('.', $token);

            $decode = fn (string $part) => json_decode(base64_decode(strtr($part, '-_', '+/')), true);

            return $decode($header)['alg'] === 'HS256'
                && $decode($header)['kid'] === '640b...'
                // The wrong audience is the single most common cause of an
                // unexplained 401 from this API.
                && $decode($payload)['aud'] === '/admin/'
                && $signature !== '';
        });
    }

    public function test_a_malformed_ghost_key_fails_before_any_request(): void
    {
        Http::fake();

        $result = (new Ghost)->verify(['site_url' => 'https://blog.example', 'admin_key' => 'not-a-pair']);

        $this->assertFalse($result->ok);
        Http::assertNothingSent();
    }

    public function test_hashnode_errors_inside_a_200_response_are_treated_as_failures(): void
    {
        [$user, $post] = $this->published();
        $this->connect($user, 'hashnode', ['token' => 'token', 'publication_id' => 'pub']);

        // GraphQL reports failure with HTTP 200 and an `errors` array.
        Http::fake(['gql.hashnode.com/*' => Http::response(['errors' => [['message' => 'Publication not found']]], 200)]);

        $this->actingAs($user)
            ->post("/posts/{$post->slug}/share/hashnode")
            ->assertSessionHas('error');
    }

    public function test_every_destination_is_reachable_from_the_registry(): void
    {
        $registry = new IntegrationRegistry;

        $this->assertCount(9, $registry->destinations());
        $this->assertCount(1, $registry->highlightSinks());
        $this->assertInstanceOf(PublishesPosts::class, $registry->find('notion'));
        $this->assertNull($registry->find('medium'));
        $this->assertNull($registry->find('pocket'));
    }
}
