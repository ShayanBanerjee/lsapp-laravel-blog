<?php

namespace Tests\Feature;

use App\Jobs\FingerprintPost;
use App\Models\CopyFlag;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CopyDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** Long enough to clear the minimum-length floor. */
    private function original(): string
    {
        return '<p>The tide goes out further here than anywhere else on this coast, and what it leaves behind is a landscape '
            .'that exists for six hours at a time. Channels that will be a river by evening are footpaths at noon.</p>'
            .'<p>People who live here read the water the way other people read a timetable, and they are never wrong twice. '
            .'The ones who get caught are always visitors, and they are always caught in the same place, on the same bar, '
            .'by the same channel filling behind them while they look at the horizon.</p>';
    }

    private function unrelated(): string
    {
        return '<p>Compilers spend most of their time in the middle end, and most of the interesting decisions are made there. '
            .'Register allocation is the part everyone remembers, but instruction selection is where the real losses are.</p>'
            .'<p>A good pass ordering will beat a clever individual pass almost every time, which is an unsatisfying result '
            .'for anyone who wanted to write the clever pass. The compiler does not care how interesting your algorithm was.</p>';
    }

    /** @return array{0: User, 1: Persona} */
    private function writer(string $handle): array
    {
        $user = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => $handle,
            'display_name' => ucfirst($handle),
        ]);

        return [$user, $persona];
    }

    private function publish(User $user, Persona $persona, string $slug, string $body): Post
    {
        $post = Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'body' => $body,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        // Queue is sync in tests, so this runs inline.
        FingerprintPost::dispatch($post->id);

        return $post;
    }

    public function test_a_verbatim_repost_is_flagged(): void
    {
        [$authorA, $personaA] = $this->writer('original');
        [$authorB, $personaB] = $this->writer('copier');

        $first = $this->publish($authorA, $personaA, 'the-tide', $this->original());
        $second = $this->publish($authorB, $personaB, 'the-tide-again', $this->original());

        $flag = CopyFlag::where('post_id', $second->id)->first();

        $this->assertNotNull($flag, 'a verbatim repost should be flagged');
        $this->assertSame($first->id, $flag->matched_post_id);
        $this->assertSame(CopyFlag::DUPLICATE, $flag->kind);
    }

    public function test_a_copied_passage_inside_original_work_is_flagged(): void
    {
        [$authorA, $personaA] = $this->writer('original');
        [$authorB, $personaB] = $this->writer('copier');

        $first = $this->publish($authorA, $personaA, 'the-tide', $this->original());

        // Their own opening, then two lifted paragraphs. SimHash alone would
        // miss this — that is what the shingle index is for.
        $mixed = '<p>I went back to the estuary last spring, mostly to see whether the path was still there.</p>'
            .$this->original();

        $second = $this->publish($authorB, $personaB, 'back-to-the-estuary', $mixed);

        $flag = CopyFlag::where('post_id', $second->id)->first();

        $this->assertNotNull($flag, 'a lifted passage should be flagged');
        $this->assertSame($first->id, $flag->matched_post_id);
        $this->assertGreaterThan(0.3, $flag->similarity);
    }

    public function test_unrelated_work_is_not_flagged(): void
    {
        [$authorA, $personaA] = $this->writer('original');
        [$authorB, $personaB] = $this->writer('other');

        $this->publish($authorA, $personaA, 'the-tide', $this->original());
        $this->publish($authorB, $personaB, 'the-middle-end', $this->unrelated());

        $this->assertDatabaseCount('copy_flags', 0);
    }

    public function test_reusing_your_own_words_is_not_flagged(): void
    {
        [$author, $persona] = $this->writer('serial');

        $this->publish($author, $persona, 'the-tide', $this->original());
        $this->publish($author, $persona, 'the-tide-revisited', $this->original());

        $this->assertDatabaseCount('copy_flags', 0);
    }

    public function test_drafts_are_neither_indexed_nor_compared(): void
    {
        [$author, $persona] = $this->writer('drafter');

        $draft = Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'unfinished',
            'title' => 'Unfinished',
            'body' => $this->original(),
            'status' => 'draft',
        ]);

        FingerprintPost::dispatch($draft->id);

        $this->assertDatabaseCount('post_fingerprints', 0);
        $this->assertDatabaseCount('post_shingles', 0);
    }

    public function test_very_short_pieces_are_not_fingerprinted(): void
    {
        [$author, $persona] = $this->writer('brief');

        $this->publish($author, $persona, 'a-note', '<p>Back soon.</p>');

        $this->assertDatabaseCount('post_fingerprints', 0);
    }

    public function test_editing_a_piece_reindexes_it(): void
    {
        [$authorA, $personaA] = $this->writer('original');
        [$authorB, $personaB] = $this->writer('reformed');

        $this->publish($authorA, $personaA, 'the-tide', $this->original());
        $copy = $this->publish($authorB, $personaB, 'the-tide-again', $this->original());

        $this->assertDatabaseCount('copy_flags', 1);

        // Rewritten into genuinely different work; the old shingles must go.
        $copy->update(['body' => $this->unrelated()]);
        FingerprintPost::dispatch($copy->id);

        $shingles = DB::table('post_shingles')->where('post_id', $copy->id)->count();
        $this->assertGreaterThan(0, $shingles);

        // The stale flag remains for review — it is a record of what was
        // published — but no second flag is raised for the new body.
        $this->assertDatabaseCount('copy_flags', 1);
    }

    public function test_publishing_through_the_editor_indexes_the_piece(): void
    {
        [$author, $persona] = $this->writer('writer');

        $this->actingAs($author)->post('/posts', [
            'title' => 'The tide',
            'body' => $this->original(),
            'persona_id' => $persona->id,
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('post_fingerprints', 1);
    }

    public function test_flags_are_visible_only_to_the_author_they_concern(): void
    {
        [$authorA, $personaA] = $this->writer('original');
        [$authorB, $personaB] = $this->writer('copier');

        $this->publish($authorA, $personaA, 'the-tide', $this->original());
        $this->publish($authorB, $personaB, 'the-tide-again', $this->original());

        // The flag belongs to the second writer's desk…
        $this->actingAs($authorB)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('copyFlags', 1));

        // …and the first writer is deliberately told nothing. A similarity
        // score is not evidence, and routing it to the other party as a
        // notification would make it one.
        $this->actingAs($authorA)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('copyFlags', 0));

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_a_writer_can_dismiss_a_flag_on_their_own_piece_and_no_one_elses(): void
    {
        [$authorA, $personaA] = $this->writer('original');
        [$authorB, $personaB] = $this->writer('copier');

        $this->publish($authorA, $personaA, 'the-tide', $this->original());
        $this->publish($authorB, $personaB, 'the-tide-again', $this->original());

        $flag = CopyFlag::firstOrFail();

        $this->actingAs($authorA)->post("/copy-flags/{$flag->id}/clear")->assertForbidden();
        $this->assertSame(CopyFlag::OPEN, $flag->fresh()->status);

        $this->actingAs($authorB)->post("/copy-flags/{$flag->id}/clear")->assertRedirect();
        $this->assertSame(CopyFlag::CLEARED, $flag->fresh()->status);

        // Dismissed flags leave the desk but stay on the record.
        $this->actingAs($authorB)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('copyFlags', 0));
        $this->assertDatabaseCount('copy_flags', 1);
    }

    public function test_deleting_a_piece_removes_it_from_the_index(): void
    {
        [$author, $persona] = $this->writer('writer');
        $post = $this->publish($author, $persona, 'the-tide', $this->original());

        $this->assertDatabaseCount('post_fingerprints', 1);

        $this->actingAs($author)->delete("/posts/{$post->slug}");

        $this->assertDatabaseCount('post_fingerprints', 0);
        $this->assertDatabaseCount('post_shingles', 0);
    }
}
