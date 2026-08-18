<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\CopyDetection;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Near-duplicate detection.
 *
 * The property under test is not "identical text matches" — an exact hash does
 * that. It is that the score survives the edits someone actually makes when
 * passing off another person's work: reordered sentences, swapped words,
 * changed punctuation and casing.
 */
class CopyDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private const ORIGINAL = 'The nearest star other than our own is four light years out, which means the light landing on your face tonight left before you had the thought that made you look up. There is no way to see the present at a distance. Every telescope is a time machine pointed the wrong way, and the further it reaches the older the news it brings back to you.';

    private function makePost(string $body, string $slug): Post
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
            'slug' => $slug,
            'title' => 'A Piece '.$slug,
            'body' => '<p>'.$body.'</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_an_identical_body_is_flagged(): void
    {
        CopyDetection::check($this->makePost(self::ORIGINAL, 'original'));

        $flags = CopyDetection::check($this->makePost(self::ORIGINAL, 'copy'));

        $this->assertCount(1, $flags);
        $this->assertSame(100, $flags->first()->containment);
    }

    /** Casing and punctuation are free to change and carry no meaning. */
    public function test_a_reformatted_copy_is_still_flagged(): void
    {
        CopyDetection::check($this->makePost(self::ORIGINAL, 'original'));

        $reformatted = strtoupper(str_replace([',', '.'], ['', ' —'], self::ORIGINAL));

        $flags = CopyDetection::check($this->makePost($reformatted, 'reformatted'));

        $this->assertCount(1, $flags, 'a reformatted copy escaped detection');
    }

    /** The whole reason for a locality-sensitive hash rather than an exact one. */
    public function test_a_lightly_edited_copy_is_still_flagged(): void
    {
        CopyDetection::check($this->makePost(self::ORIGINAL, 'original'));

        $edited = str_replace(
            ['nearest star', 'time machine', 'telescope'],
            ['closest star', 'time device', 'lens'],
            self::ORIGINAL,
        );

        $flags = CopyDetection::check($this->makePost($edited, 'edited'));

        $this->assertCount(1, $flags, 'a lightly edited copy escaped detection');
    }

    public function test_unrelated_writing_is_not_flagged(): void
    {
        CopyDetection::check($this->makePost(self::ORIGINAL, 'original'));

        $other = 'Moss does not race, it occupies. There is a difference between growth that competes for a place and growth that simply outlasts everything else standing in it, and the north wall of a house is where you can watch the second kind win over a single wet autumn.';

        $flags = CopyDetection::check($this->makePost($other, 'unrelated'));

        $this->assertCount(0, $flags);
    }

    /** Two short pieces sharing a stock phrase must not flag each other. */
    public function test_very_short_pieces_are_not_fingerprinted(): void
    {
        $flags = CopyDetection::check($this->makePost('Thanks for reading.', 'short'));

        $this->assertCount(0, $flags);
        $this->assertDatabaseCount('post_fingerprints', 0);
    }

    /** Nothing is ever removed or blocked automatically. */
    public function test_a_flagged_piece_stays_published_and_readable(): void
    {
        CopyDetection::check($this->makePost(self::ORIGINAL, 'original'));
        $copy = $this->makePost(self::ORIGINAL, 'copy');
        CopyDetection::check($copy);

        $this->get('/posts/copy')->assertOk();
        $this->assertSame('published', $copy->fresh()->status);
        $this->assertDatabaseHas('duplicate_flags', ['post_id' => $copy->id, 'status' => 'pending']);
    }

    public function test_publishing_through_the_editor_fingerprints_the_piece(): void
    {
        $author = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'writerx',
            'display_name' => 'Writer',
        ]);

        $this->actingAs($author)->post('/posts', [
            'persona_id' => $persona->id,
            'title' => 'A New Piece',
            'body' => '<p>'.self::ORIGINAL.'</p>',
            'status' => 'published',
        ])->assertRedirect();

        $this->assertDatabaseCount('post_fingerprints', 1);
    }

    /** A draft cannot have been copied from, so it is not fingerprinted. */
    public function test_drafts_are_not_fingerprinted(): void
    {
        $author = User::factory()->create();
        $persona = Persona::create([
            'user_id' => $author->id,
            'universe_id' => Universe::where('slug', 'cosmos')->value('id'),
            'handle' => 'writery',
            'display_name' => 'Writer',
        ]);

        $this->actingAs($author)->post('/posts', [
            'persona_id' => $persona->id,
            'title' => 'A Draft',
            'body' => '<p>'.self::ORIGINAL.'</p>',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseCount('post_fingerprints', 0);
    }

    /**
     * A copied paragraph inside an otherwise original piece.
     *
     * This is the case SimHash could not see at all, and the reason this uses
     * containment rather than Jaccard — by Jaccard the pair scores 0.26, which
     * is indistinguishable from noise.
     */
    public function test_a_single_lifted_paragraph_is_flagged(): void
    {
        CopyDetection::check($this->makePost(self::ORIGINAL, 'original'));

        $partial = 'A completely different opening paragraph about something else entirely, written from scratch. '
            .'Every telescope is a time machine pointed the wrong way, and the further it reaches the older the news it brings back to you. '
            .'And then a different closing thought that has nothing to do with any of the above.';

        $flags = CopyDetection::check($this->makePost($partial, 'partial'));

        $this->assertCount(1, $flags, 'a lifted paragraph escaped detection');
    }

    /** Editing a piece must not leave it matching phrases it no longer has. */
    public function test_rewriting_a_piece_clears_its_old_shingles(): void
    {
        $post = $this->makePost(self::ORIGINAL, 'original');
        CopyDetection::check($post);

        $before = \DB::table('post_shingles')->where('post_id', $post->id)->count();

        $post->update(['body' => '<p>Moss does not race, it occupies, and the north wall of a house is where you can watch that kind of growth outlast everything else standing in it over a single wet autumn season.</p>']);
        CopyDetection::check($post->fresh());

        $after = \DB::table('post_shingles')->where('post_id', $post->id)->count();

        $this->assertGreaterThan(0, $after);
        // Nothing from the old body survives.
        $this->assertSame(0, CopyDetection::check($this->makePost(self::ORIGINAL, 'fresh-original'))->count());
        $this->assertNotSame($before, $after);
    }
}
