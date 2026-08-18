<?php

namespace Tests\Feature;

use App\Models\Highlight;
use App\Models\Integration;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\Integrations\ManuscriptExporter;
use App\Support\Integrations\MarkdownExporter;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
        // No test may reach the real Readwise API.
        Http::preventStrayRequests();
    }

    private function makePost(): Post
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
            'slug' => 'a-piece',
            'title' => 'A Piece: With a Colon',
            'body' => '<h2>Heading</h2><p>Some <strong>bold</strong> text and a <a href="https://example.com">link</a>.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    /* -------------------- export -------------------- */

    public function test_a_piece_exports_as_markdown_with_frontmatter(): void
    {
        $post = $this->makePost();

        $body = $this->actingAs(User::factory()->create())
            ->get("/posts/{$post->slug}/export.md")
            ->assertOk()
            ->assertHeader('content-type', 'text/markdown; charset=UTF-8')
            ->streamedContent();

        $this->assertStringStartsWith('---', $body);
        $this->assertStringContainsString('## Heading', $body);
        $this->assertStringContainsString('**bold**', $body);
        $this->assertStringContainsString('[link](https://example.com)', $body);
        $this->assertStringNotContainsString('<p>', $body);
    }

    /** A colon in a title would otherwise produce invalid YAML. */
    public function test_frontmatter_quotes_values_that_would_break_yaml(): void
    {
        $post = $this->makePost();

        $markdown = MarkdownExporter::forPost($post->load(['persona', 'universe', 'user']));

        $this->assertStringContainsString('title: "A Piece: With a Colon"', $markdown);
    }

    public function test_a_reader_can_export_only_their_own_marks(): void
    {
        $post = $this->makePost();
        $reader = User::factory()->create();
        $other = User::factory()->create();

        Highlight::create([
            'post_id' => $post->id, 'user_id' => $reader->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 4, 'quote' => 'mine',
        ]);
        Highlight::create([
            'post_id' => $post->id, 'user_id' => $other->id,
            'block_index' => 0, 'start_offset' => 5, 'end_offset' => 9, 'quote' => 'theirs',
        ]);

        $body = $this->actingAs($reader)->get("/posts/{$post->slug}/highlights.md")->assertOk()->streamedContent();

        $this->assertStringContainsString('> mine', $body);
        $this->assertStringNotContainsString('theirs', $body);
    }

    /* -------------------- readwise -------------------- */

    public function test_a_bad_token_is_rejected_before_it_is_stored(): void
    {
        Http::fake(['readwise.io/api/v2/auth/' => Http::response('', 401)]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/settings/integrations', ['service' => 'readwise', 'token' => 'wrong'])
            ->assertRedirect();

        $this->assertDatabaseCount('integrations', 0);
    }

    public function test_a_verified_token_is_stored_encrypted(): void
    {
        Http::fake(['readwise.io/api/v2/auth/' => Http::response('', 204)]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/integrations', ['service' => 'readwise', 'token' => 'secret-token']);

        $this->assertDatabaseHas('integrations', ['user_id' => $user->id, 'service' => 'readwise']);

        // Encrypted at rest: the raw column must not contain the token.
        $raw = DB::table('integrations')->where('user_id', $user->id)->value('token');
        $this->assertNotSame('secret-token', $raw);
        $this->assertStringNotContainsString('secret-token', (string) $raw);

        // …but it decrypts back through the model.
        $this->assertSame('secret-token', Integration::where('user_id', $user->id)->first()->token);
    }

    /** The credential must never be serialized into a page. */
    public function test_the_token_is_never_sent_to_the_browser(): void
    {
        Http::fake(['readwise.io/api/v2/auth/' => Http::response('', 204)]);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/settings/integrations', ['service' => 'readwise', 'token' => 'secret-token']);

        $html = $this->actingAs($user)->get('/settings/integrations')->assertOk()->getContent();

        $this->assertStringNotContainsString('secret-token', $html);
    }

    public function test_marks_are_pushed_to_readwise(): void
    {
        Http::fake([
            'readwise.io/api/v2/auth/' => Http::response('', 204),
            'readwise.io/api/v2/highlights/' => Http::response(['id' => 1], 200),
        ]);

        $post = $this->makePost();
        $reader = User::factory()->create();

        Highlight::create([
            'post_id' => $post->id, 'user_id' => $reader->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 4, 'quote' => 'a passage',
        ]);

        Integration::create(['user_id' => $reader->id, 'service' => 'readwise', 'token' => 'good']);

        $this->actingAs($reader)->post('/settings/integrations/sync')->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'highlights/')
            && $request['highlights'][0]['text'] === 'a passage'
            && str_contains($request['highlights'][0]['source_url'], $post->slug));

        $this->assertNotNull(Integration::where('user_id', $reader->id)->first()->last_synced_at);
    }

    /** A third party being down is not a 500 here. */
    public function test_a_readwise_outage_surfaces_as_a_message_not_an_error(): void
    {
        Http::fake(['readwise.io/api/v2/highlights/' => Http::response('', 503)]);

        $post = $this->makePost();
        $reader = User::factory()->create();

        Highlight::create([
            'post_id' => $post->id, 'user_id' => $reader->id,
            'block_index' => 0, 'start_offset' => 0, 'end_offset' => 4, 'quote' => 'a passage',
        ]);

        Integration::create(['user_id' => $reader->id, 'service' => 'readwise', 'token' => 'good']);

        $this->actingAs($reader)->post('/settings/integrations/sync')
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_disconnecting_removes_the_credential(): void
    {
        $user = User::factory()->create();
        Integration::create(['user_id' => $user->id, 'service' => 'readwise', 'token' => 'good']);

        $this->actingAs($user)->delete('/settings/integrations/readwise')->assertRedirect();

        $this->assertDatabaseCount('integrations', 0);
    }

    /* -------------------- manuscript formats -------------------- */

    public function test_a_piece_exports_as_ieee_latex(): void
    {
        $post = $this->makePost();

        $tex = $this->actingAs(User::factory()->create())
            ->get("/posts/{$post->slug}/manuscript.tex")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('\\documentclass[conference]{IEEEtran}', $tex);
        $this->assertStringContainsString('\\begin{document}', $tex);
        $this->assertStringContainsString('\\end{document}', $tex);
        $this->assertStringContainsString('\\section{Heading}', $tex);
        $this->assertStringContainsString('\\textbf{bold}', $tex);
    }

    /**
     * TeX treats these as syntax. An unescaped one does not produce a slightly
     * wrong PDF — it fails to compile, on the author's deadline.
     */
    public function test_latex_escapes_tex_control_characters_in_the_title(): void
    {
        $post = $this->makePost();
        $post->update(['title' => 'Cost & Benefit: 50% of $5 #1 a_b']);

        $tex = ManuscriptExporter::toLatex($post->fresh()->load(['persona', 'user']));

        $this->assertStringContainsString('\\title{Cost \\& Benefit: 50\\% of \\$5 \\#1 a\\_b}', $tex);
    }

    public function test_a_piece_exports_as_a_readable_docx(): void
    {
        $post = $this->makePost();

        $binary = $this->actingAs(User::factory()->create())
            ->get("/posts/{$post->slug}/manuscript.docx")
            ->assertOk()
            ->streamedContent();

        // A real zip, with the parts Word requires, containing the text.
        $file = tempnam(sys_get_temp_dir(), 'docx-test');
        file_put_contents($file, $binary);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file) === true, 'export is not a valid archive');

        $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
        $this->assertNotFalse($zip->locateName('_rels/.rels'));

        $document = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('A Piece', $document);
        $this->assertStringContainsString('Heading', $document);

        $zip->close();
        unlink($file);
    }

    /* -------------------- zenodo -------------------- */

    public function test_a_deposit_creates_a_draft_and_never_publishes_it(): void
    {
        Http::fake([
            '*/deposit/depositions' => Http::response(['id' => 4242, 'links' => ['html' => 'https://sandbox.zenodo.org/deposit/4242']], 201),
        ]);

        $post = $this->makePost();
        Integration::create(['user_id' => $post->user_id, 'service' => 'zenodo', 'token' => 'zen']);

        $this->actingAs($post->user)->post("/posts/{$post->slug}/deposit")
            ->assertRedirect()
            ->assertSessionHas('success');

        // A DOI is permanent; publishing stays a human decision on their page.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/actions/publish'));
    }

    public function test_only_the_author_can_deposit_a_piece(): void
    {
        $post = $this->makePost();

        $this->actingAs(User::factory()->create())
            ->post("/posts/{$post->slug}/deposit")
            ->assertForbidden();
    }

    /* -------------------- crossref -------------------- */

    public function test_a_doi_resolves_to_a_citation(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response(['message' => [
                'DOI' => '10.1000/xyz123',
                'title' => ['On Addressable Text'],
                'author' => [['family' => 'Lovelace', 'given' => 'Ada']],
                'issued' => ['date-parts' => [[1843]]],
                'container-title' => ['Notes'],
                'URL' => 'https://doi.org/10.1000/xyz123',
            ]], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/settings/citation', ['doi' => 'https://doi.org/10.1000/xyz123'])
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Lovelace, Ada')
                && str_contains($message, '1843')
                && str_contains($message, 'On Addressable Text'));
    }

    public function test_a_malformed_doi_is_rejected_without_a_request(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/settings/citation', ['doi' => 'not-a-doi'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }
}
