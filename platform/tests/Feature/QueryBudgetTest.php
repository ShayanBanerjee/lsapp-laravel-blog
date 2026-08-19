<?php

namespace Tests\Feature;

use App\Models\Highlight;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Response;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\CircleSeeder;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Query budgets.
 *
 * These are the tests that stop the app falling over under concurrency. An N+1
 * is not merely a slow page: at load it multiplies into connection-pool
 * exhaustion, and the symptom shows up as timeouts everywhere rather than
 * slowness on the page that caused it.
 *
 * The budgets are deliberately a little loose — they are a ratchet against
 * regressions, not a golden-file assertion that breaks on every refactor. What
 * matters is that the count does not grow with the number of rows.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
        $this->seed(CircleSeeder::class);

        // Warm the universe cache before any measurement. Otherwise the first
        // request pays for it and the second does not, and every budget
        // comparison is measuring cache warmth rather than query fan-out.
        Universe::cachedAll();
    }

    /** @return array{0: User, 1: Persona} */
    private function writer(): array
    {
        $user = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'writer'.$user->id,
            'display_name' => 'Writer',
        ]);

        return [$user, $persona];
    }

    private function seedPosts(int $count): void
    {
        [$user, $persona] = $this->writer();

        foreach (range(1, $count) as $index) {
            Post::create([
                'user_id' => $user->id,
                'persona_id' => $persona->id,
                'universe_id' => $persona->universe_id,
                'slug' => "piece-{$index}",
                'title' => "Piece {$index}",
                'body' => '<p>Body.</p>',
                'status' => 'published',
                'published_at' => now()->subDays($index),
            ]);
        }
    }

    /** @return int number of queries executed while running $callback */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * The decisive property: query count must not grow with row count. If it
     * does, this is an N+1 regardless of what the absolute number is.
     */
    public function test_the_feed_does_not_issue_more_queries_as_posts_are_added(): void
    {
        $this->seedPosts(3);
        $small = $this->countQueries(fn () => $this->get('/posts')->assertOk());

        // Fresh set of posts by a different author, so persona/universe joins
        // would fan out if they were being lazy-loaded.
        $this->seedPosts2(9);
        $large = $this->countQueries(fn () => $this->get('/posts')->assertOk());

        // Growth is the failure. A decrease (from caching) is fine.
        $this->assertLessThanOrEqual(
            $small,
            $large,
            "Feed query count grew from {$small} to {$large} as posts were added — that is an N+1."
        );
    }

    private function seedPosts2(int $count): void
    {
        [$user, $persona] = $this->writer();

        foreach (range(1, $count) as $index) {
            Post::create([
                'user_id' => $user->id,
                'persona_id' => $persona->id,
                'universe_id' => $persona->universe_id,
                'slug' => "extra-{$index}",
                'title' => "Extra {$index}",
                'body' => '<p>Body.</p>',
                'status' => 'published',
                'published_at' => now()->subDays($index),
            ]);
        }
    }

    /**
     * Both measurements are taken with data already present.
     *
     * Comparing an empty post against a populated one would be a false
     * positive: Eloquent skips an eager-load query entirely when the parent
     * collection is empty, so the count legitimately rises by a constant the
     * first time any response exists. The regression worth catching is growth
     * *proportional to rows*, which is what doubling the data measures.
     */
    public function test_a_post_page_does_not_fan_out_with_marks_or_responses(): void
    {
        $this->seedPosts(4);
        $post = Post::firstWhere('slug', 'piece-1');

        $this->addReaders($post, 4);
        $withFour = $this->countQueries(fn () => $this->get("/posts/{$post->slug}")->assertOk());

        $this->addReaders($post, 16);
        $withTwenty = $this->countQueries(fn () => $this->get("/posts/{$post->slug}")->assertOk());

        $this->assertLessThanOrEqual(
            $withFour,
            $withTwenty,
            "Post page went from {$withFour} to {$withTwenty} queries when readers grew from 4 to 20 — that is an N+1."
        );
    }

    private function addReaders(Post $post, int $count): void
    {
        $offset = Highlight::where('post_id', $post->id)->count();

        foreach (range(1, $count) as $index) {
            $reader = User::factory()->create();
            $at = $offset + $index;

            Highlight::create([
                'post_id' => $post->id, 'user_id' => $reader->id,
                'block_index' => 0, 'start_offset' => $at, 'end_offset' => $at + 4, 'quote' => 'Body',
            ]);
            Response::create([
                'post_id' => $post->id, 'user_id' => $reader->id, 'body' => "Response {$at}.",
            ]);
        }
    }

    public function test_the_universe_index_does_not_fan_out(): void
    {
        $this->seedPosts(6);

        $count = $this->countQueries(fn () => $this->get('/universes')->assertOk());

        // Six universes; anything approaching one query per universe means the
        // counts or previews are being loaded per row.
        $this->assertLessThan(12, $count, "Universe index used {$count} queries for 6 universes.");
    }

    public function test_the_circles_index_does_not_fan_out(): void
    {
        $count = $this->countQueries(fn () => $this->get('/circles')->assertOk());

        $this->assertLessThan(12, $count, "Circle index used {$count} queries.");
    }

    public function test_the_following_feed_does_not_fan_out(): void
    {
        $reader = User::factory()->create();

        $this->seedPosts(3);
        Persona::firstOrFail()->followers()->create(['user_id' => $reader->id]);

        $small = $this->countQueries(fn () => $this->actingAs($reader)->get('/following')->assertOk());

        $this->seedPosts2(9);
        Persona::latest('id')->firstOrFail()->followers()->create(['user_id' => $reader->id]);

        $large = $this->countQueries(fn () => $this->actingAs($reader)->get('/following')->assertOk());

        $this->assertLessThanOrEqual(
            $small,
            $large,
            "Following-feed query count grew from {$small} to {$large} — that is an N+1."
        );
    }

    public function test_a_profile_does_not_fan_out(): void
    {
        $this->seedPosts(3);
        $handle = Persona::firstOrFail()->handle;

        $small = $this->countQueries(fn () => $this->get("/@{$handle}")->assertOk());

        // More work by the same voice: the page must cost the same to render.
        $this->seedPostsFor(Persona::firstOrFail(), 9);

        $large = $this->countQueries(fn () => $this->get("/@{$handle}")->assertOk());

        $this->assertLessThanOrEqual(
            $small,
            $large,
            "Profile query count grew from {$small} to {$large} — that is an N+1."
        );
    }

    private function seedPostsFor(Persona $persona, int $count): void
    {
        foreach (range(1, $count) as $index) {
            Post::create([
                'user_id' => $persona->user_id,
                'persona_id' => $persona->id,
                'universe_id' => $persona->universe_id,
                'slug' => "more-{$index}",
                'title' => "More {$index}",
                'body' => '<p>Body.</p>',
                'status' => 'published',
                'published_at' => now()->subDays($index),
            ]);
        }
    }
}
