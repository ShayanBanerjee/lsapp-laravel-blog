<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    private function writer(bool $premium = false, string $universe = 'cosmos'): array
    {
        $user = User::factory()->create(['is_premium' => $premium]);
        $persona = Persona::create([
            'user_id' => $user->id,
            'universe_id' => Universe::where('slug', $universe)->value('id'),
            'handle' => 'writer'.$user->id,
            'display_name' => 'Writer',
        ]);

        return [$user, $persona];
    }

    public function test_guest_can_read_published_posts(): void
    {
        [$user, $persona] = $this->writer();

        Post::create([
            'user_id' => $user->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'a-published-piece',
            'title' => 'A Published Piece',
            'body' => '<p>Body</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get('/posts/a-published-piece')->assertOk();
        $this->get('/posts')->assertOk();
    }

    public function test_drafts_are_hidden_from_everyone_but_their_author(): void
    {
        [$author, $persona] = $this->writer();
        $stranger = User::factory()->create();

        Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'unfinished',
            'title' => 'Unfinished',
            'body' => '<p>Body</p>',
            'status' => 'draft',
        ]);

        $this->get('/posts/unfinished')->assertForbidden();
        $this->actingAs($stranger)->get('/posts/unfinished')->assertForbidden();
        $this->actingAs($author)->get('/posts/unfinished')->assertOk();
    }

    public function test_a_writer_can_publish_a_post_with_a_cover_image(): void
    {
        Storage::fake('public');
        [$user, $persona] = $this->writer();

        $this->actingAs($user)->post('/posts', [
            'title' => 'The Long Light',
            'body' => '<p>Something worth saying.</p>',
            'persona_id' => $persona->id,
            'status' => 'published',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertRedirect();

        $post = Post::firstWhere('slug', 'the-long-light');

        $this->assertNotNull($post);
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame($persona->universe_id, $post->universe_id);

        // The stored value is the disk-relative path, and it actually exists —
        // the legacy app wrote to one directory and read from another.
        Storage::disk('public')->assertExists($post->cover_image);
        $this->assertStringStartsWith('cover-images/', $post->cover_image);
    }

    public function test_deleting_a_post_removes_its_cover_image(): void
    {
        Storage::fake('public');
        [$user, $persona] = $this->writer();

        $this->actingAs($user)->post('/posts', [
            'title' => 'Temporary',
            'body' => '<p>Body</p>',
            'persona_id' => $persona->id,
            'status' => 'published',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $post = Post::firstWhere('slug', 'temporary');
        $path = $post->cover_image;

        $this->actingAs($user)->delete("/posts/{$post->slug}")->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_a_stranger_cannot_edit_or_delete_someone_elses_post(): void
    {
        [$author, $persona] = $this->writer();
        $stranger = User::factory()->create();

        $post = Post::create([
            'user_id' => $author->id,
            'persona_id' => $persona->id,
            'universe_id' => $persona->universe_id,
            'slug' => 'not-yours',
            'title' => 'Not Yours',
            'body' => '<p>Body</p>',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($stranger)->get("/posts/{$post->slug}/edit")->assertForbidden();
        $this->actingAs($stranger)->delete("/posts/{$post->slug}")->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_slugs_stay_unique_across_identical_titles(): void
    {
        [$user, $persona] = $this->writer();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($user)->post('/posts', [
                'title' => 'Same Title',
                'body' => '<p>Body</p>',
                'persona_id' => $persona->id,
                'status' => 'published',
            ]);
        }

        $this->assertSame(3, Post::where('title', 'Same Title')->count());
        $this->assertSame(3, Post::whereIn('slug', ['same-title', 'same-title-2', 'same-title-3'])->count());
    }

    /**
     * cover_image holds two shapes: uploads keep a disk-relative path, seeded
     * imagery keeps an absolute public path. Post::coverUrl() is the only place
     * that difference is allowed to matter.
     */
    public function test_cover_urls_resolve_for_both_uploads_and_bundled_imagery(): void
    {
        [$user, $persona] = $this->writer();

        $bundled = Post::create([
            'user_id' => $user->id, 'persona_id' => $persona->id, 'universe_id' => $persona->universe_id,
            'slug' => 'bundled', 'title' => 'Bundled', 'body' => '<p>b</p>',
            'cover_image' => '/images/covers/cosmos-1.jpg', 'status' => 'published', 'published_at' => now(),
        ]);

        $uploaded = Post::create([
            'user_id' => $user->id, 'persona_id' => $persona->id, 'universe_id' => $persona->universe_id,
            'slug' => 'uploaded', 'title' => 'Uploaded', 'body' => '<p>b</p>',
            'cover_image' => 'cover-images/x.jpg', 'status' => 'published', 'published_at' => now(),
        ]);

        // Absolute paths are served as-is, never prefixed with /storage.
        $this->assertSame('/images/covers/cosmos-1.jpg', $bundled->coverUrl());
        $this->assertStringContainsString('/storage/cover-images/x.jpg', $uploaded->coverUrl());

        $this->assertFalse($bundled->hasUploadedCover());
        $this->assertTrue($uploaded->hasUploadedCover());
    }

    public function test_deleting_a_post_never_removes_bundled_seed_imagery(): void
    {
        Storage::fake('public');
        [$user, $persona] = $this->writer();

        $post = Post::create([
            'user_id' => $user->id, 'persona_id' => $persona->id, 'universe_id' => $persona->universe_id,
            'slug' => 'seeded', 'title' => 'Seeded', 'body' => '<p>b</p>',
            'cover_image' => '/images/covers/cosmos-1.jpg', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs($user)->delete("/posts/{$post->slug}")->assertRedirect();

        // The shared file on the public path must survive — it is not ours.
        $this->assertFileExists(public_path('images/covers/cosmos-1.jpg'));
    }

    public function test_a_writer_cannot_publish_through_someone_elses_persona(): void
    {
        [, $victimPersona] = $this->writer();
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->post('/posts', [
            'title' => 'Impersonation',
            'body' => '<p>Body</p>',
            'persona_id' => $victimPersona->id,
            'status' => 'published',
        ])->assertNotFound();

        $this->assertDatabaseMissing('posts', ['title' => 'Impersonation']);
    }

    /**
     * Storytelling blocks have to survive the sanitiser on the way in, or the
     * editor silently produces markup the reader never sees. The security
     * behaviour of each attribute is covered in HtmlSanitizerTest; this is the
     * round trip through an actual publish.
     */
    public function test_storytelling_blocks_survive_publishing(): void
    {
        [$user, $persona] = $this->writer();

        $body = '<p>Opening.</p>'
            .'<figure data-story="callout" data-story-value="4.2" data-story-label="light years">'
            .'<p>Context for the number.</p></figure>'
            .'<figure data-story="pinned"><img src="/images/covers/cosmos-1.jpg" alt="Stars">'
            .'<p>Text that moves past it.</p></figure>';

        $this->actingAs($user)->post('/posts', [
            'persona_id' => $persona->id,
            'title' => 'A Piece With Blocks',
            'body' => $body,
            'status' => 'published',
        ])->assertRedirect();

        $stored = Post::where('title', 'A Piece With Blocks')->firstOrFail()->body;

        $this->assertStringContainsString('data-story="callout"', $stored);
        $this->assertStringContainsString('data-story-value="4.2"', $stored);
        $this->assertStringContainsString('data-story="pinned"', $stored);
        $this->assertStringContainsString('src="/images/covers/cosmos-1.jpg"', $stored);
    }
}
