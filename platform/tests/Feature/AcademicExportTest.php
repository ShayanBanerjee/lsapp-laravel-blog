<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\Academic\Crossref;
use App\Support\Academic\Manuscript;
use App\Support\Academic\Orcid;
use App\Support\Academic\Zenodo;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class AcademicExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{0: User, 1: Post} */
    private function published(array $userAttributes = []): array
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', ...$userAttributes]);
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
            'slug' => 'on-the-analytical-engine',
            'title' => 'On the Analytical Engine & 100% of its uses',
            'excerpt' => 'A note about a machine.',
            'body' => '<h2>The engine</h2><p>It weaves algebraic patterns.</p>'
                .'<ul><li>First</li><li>Second</li></ul>'
                .'<blockquote><p>A quotation with a $ sign and a #hash.</p></blockquote>',
            'status' => 'published',
            'reading_time' => 3,
            'published_at' => now()->subDay(),
        ]);

        return [$user, $post];
    }

    public function test_markdown_export_carries_yaml_frontmatter(): void
    {
        [, $post] = $this->published();

        $response = $this->get("/posts/{$post->slug}/export/markdown");
        $response->assertOk();

        $body = $response->streamedContent();

        $this->assertStringStartsWith("---\n", $body);
        // Quoted, because an unquoted title containing a colon or a leading #
        // is a different YAML document than the author meant.
        $this->assertStringContainsString('title: "On the Analytical Engine & 100% of its uses"', $body);
        $this->assertStringContainsString('## The engine', $body);
        $this->assertStringContainsString('- First', $body);
    }

    public function test_latex_export_escapes_every_special_character(): void
    {
        [$user, $post] = $this->published();

        $response = $this->actingAs($user)->get("/posts/{$post->slug}/export/latex");
        $response->assertOk();

        $body = $response->streamedContent();

        $this->assertStringContainsString('\documentclass[conference]{IEEEtran}', $body);
        // An unescaped & or % here is a file that does not compile.
        $this->assertStringContainsString('\& 100\%', $body);
        $this->assertStringContainsString('\section{The engine}', $body);
        $this->assertStringContainsString('\begin{itemize}', $body);
        $this->assertStringContainsString('\$ sign', $body);
        $this->assertStringContainsString('\#hash', $body);
    }

    public function test_docx_export_is_a_readable_office_package(): void
    {
        [$user, $post] = $this->published();

        $response = $this->actingAs($user)->get("/posts/{$post->slug}/export/docx");
        $response->assertOk();

        $file = tempnam(sys_get_temp_dir(), 'docx-test-');
        file_put_contents($file, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true, 'the export should be a valid zip');

        // The four parts Word needs before it will open anything.
        foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml', 'word/styles.xml'] as $part) {
            $this->assertNotFalse($zip->locateName($part), "missing {$part}");
        }

        $document = $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);

        // Well-formed XML, and the ampersand escaped — Word rejects the whole
        // file as corrupt otherwise, with no useful error.
        $this->assertNotFalse(simplexml_load_string($document));
        $this->assertStringContainsString('&amp;', $document);
        $this->assertStringContainsString('It weaves algebraic patterns.', $document);
    }

    public function test_bibtex_export_is_a_well_formed_entry(): void
    {
        [, $post] = $this->published();

        $body = $this->get("/posts/{$post->slug}/export/bibtex")->streamedContent();

        $this->assertStringContainsString('@misc{lovelace', $body);
        $this->assertStringContainsString('\url{', $body);
        // Braces balance, or the rest of the bibliography silently disappears.
        $this->assertSame(substr_count($body, '{'), substr_count($body, '}'));
    }

    public function test_manuscript_formats_are_the_authors_alone(): void
    {
        [, $post] = $this->published();
        $stranger = User::factory()->create();

        foreach (['latex', 'docx'] as $format) {
            $this->get("/posts/{$post->slug}/export/{$format}")->assertForbidden();
            $this->actingAs($stranger)->get("/posts/{$post->slug}/export/{$format}")->assertForbidden();
        }

        // Citation and Markdown are open, because reading is.
        foreach (['bibtex', 'markdown'] as $format) {
            $this->get("/posts/{$post->slug}/export/{$format}")->assertOk();
        }
    }

    public function test_a_draft_cannot_be_exported_by_anyone_else(): void
    {
        [$user, $post] = $this->published();
        $post->update(['status' => 'draft', 'published_at' => null]);

        $this->get("/posts/{$post->slug}/export/markdown")->assertForbidden();
        $this->actingAs($user)->get("/posts/{$post->slug}/export/markdown")->assertOk();
    }

    public function test_an_unknown_format_is_a_404(): void
    {
        [, $post] = $this->published();

        $this->get("/posts/{$post->slug}/export/pdf")->assertNotFound();
    }

    public function test_an_orcid_id_appears_in_the_manuscript_when_linked(): void
    {
        [$user, $post] = $this->published(['orcid_id' => '0000-0002-1825-0097']);

        $latex = $this->actingAs($user)->get("/posts/{$post->slug}/export/latex")->streamedContent();
        $markdown = $this->get("/posts/{$post->slug}/export/markdown")->streamedContent();

        $this->assertStringContainsString('0000-0002-1825-0097', $latex);
        $this->assertStringContainsString('orcid: "0000-0002-1825-0097"', $markdown);
    }

    public function test_orcid_ids_are_checked_against_their_own_checksum(): void
    {
        // The canonical example iD from ORCID's own documentation.
        $this->assertTrue(Orcid::isValidId('0000-0002-1825-0097'));

        // Right shape, wrong check digit — catches a typo without a round trip.
        $this->assertFalse(Orcid::isValidId('0000-0002-1825-0098'));
        $this->assertFalse(Orcid::isValidId('not-an-orcid'));
        $this->assertFalse(Orcid::isValidId('0000000218250097'));
    }

    public function test_orcid_endpoints_are_inert_without_configuration(): void
    {
        config(['services.orcid.client_id' => null, 'services.orcid.client_secret' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/auth/orcid/redirect')->assertStatus(503);
    }

    public function test_an_orcid_callback_with_a_mismatched_state_is_refused(): void
    {
        config(['services.orcid.client_id' => 'id', 'services.orcid.client_secret' => 'secret']);
        $user = User::factory()->create();

        // No state was ever put in the session, so nothing can match it.
        $this->actingAs($user)
            ->get('/auth/orcid/callback?code=abc&state=forged')
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->fresh()->orcid_id);
    }

    public function test_an_orcid_id_already_claimed_cannot_be_taken(): void
    {
        config([
            'services.orcid.client_id' => 'id',
            'services.orcid.client_secret' => 'secret',
            'services.orcid.host' => 'https://sandbox.orcid.org',
        ]);

        User::factory()->create(['orcid_id' => '0000-0002-1825-0097']);
        $second = User::factory()->create();

        Http::fake([
            'sandbox.orcid.org/oauth/token' => Http::response([
                'orcid' => '0000-0002-1825-0097',
                'name' => 'Ada Lovelace',
            ]),
        ]);

        $this->actingAs($second)
            ->withSession(['orcid_state' => 'known-state'])
            ->get('/auth/orcid/callback?code=abc&state=known-state')
            ->assertSessionHas('error');

        $this->assertNull($second->fresh()->orcid_id);
    }

    public function test_a_valid_orcid_callback_links_the_account(): void
    {
        config([
            'services.orcid.client_id' => 'id',
            'services.orcid.client_secret' => 'secret',
            'services.orcid.host' => 'https://sandbox.orcid.org',
        ]);

        $user = User::factory()->create();

        Http::fake([
            'sandbox.orcid.org/oauth/token' => Http::response([
                'orcid' => '0000-0002-1825-0097',
                'name' => 'Ada Lovelace',
            ]),
        ]);

        $this->actingAs($user)
            ->withSession(['orcid_state' => 'known-state'])
            ->get('/auth/orcid/callback?code=abc&state=known-state')
            ->assertSessionHas('success');

        $this->assertSame('0000-0002-1825-0097', $user->fresh()->orcid_id);
    }

    public function test_an_orcid_that_fails_its_checksum_is_rejected_even_from_the_provider(): void
    {
        config([
            'services.orcid.client_id' => 'id',
            'services.orcid.client_secret' => 'secret',
            'services.orcid.host' => 'https://sandbox.orcid.org',
        ]);

        $user = User::factory()->create();

        Http::fake(['sandbox.orcid.org/oauth/token' => Http::response(['orcid' => '9999-9999-9999-9999'])]);

        $this->actingAs($user)
            ->withSession(['orcid_state' => 'known-state'])
            ->get('/auth/orcid/callback?code=abc&state=known-state')
            ->assertSessionHas('error');

        $this->assertNull($user->fresh()->orcid_id);
    }

    public function test_zenodo_is_inert_without_a_token(): void
    {
        config(['services.zenodo.token' => null]);
        [$user, $post] = $this->published();

        $this->assertFalse(Zenodo::isConfigured());
        $this->actingAs($user)->post("/posts/{$post->slug}/deposit")->assertStatus(503);
    }

    public function test_a_zenodo_deposit_creates_a_draft_and_does_not_publish_it(): void
    {
        config(['services.zenodo.token' => 'test-token', 'services.zenodo.host' => 'https://sandbox.zenodo.org']);
        [$user, $post] = $this->published();

        Http::fake([
            'sandbox.zenodo.org/api/deposit/depositions' => Http::response([
                'id' => 4242,
                'metadata' => ['prereserve_doi' => ['doi' => '10.5281/zenodo.4242']],
                'links' => ['bucket' => 'https://sandbox.zenodo.org/api/files/abc', 'html' => 'https://sandbox.zenodo.org/deposit/4242'],
            ]),
            'sandbox.zenodo.org/api/files/*' => Http::response([], 201),
        ]);

        $this->actingAs($user)->post("/posts/{$post->slug}/deposit")->assertSessionHas('success');

        // Minting the DOI is irreversible and is deliberately not done here.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/actions/publish'));
    }

    public function test_a_draft_is_never_deposited(): void
    {
        config(['services.zenodo.token' => 'test-token']);
        [$user, $post] = $this->published();
        $post->update(['status' => 'draft', 'published_at' => null]);

        Http::fake();

        $this->actingAs($user)->post("/posts/{$post->slug}/deposit")->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_only_the_author_can_deposit(): void
    {
        config(['services.zenodo.token' => 'test-token']);
        [, $post] = $this->published();
        $stranger = User::factory()->create();

        Http::fake();

        $this->actingAs($stranger)->post("/posts/{$post->slug}/deposit")->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_crossref_normalises_the_forms_people_actually_paste(): void
    {
        foreach ([
            '10.1000/xyz123',
            'https://doi.org/10.1000/xyz123',
            'https://dx.doi.org/10.1000/xyz123',
            'doi:10.1000/xyz123',
        ] as $input) {
            $this->assertSame('10.1000/xyz123', Crossref::normalize($input), "failed on {$input}");
        }

        $this->assertNull(Crossref::normalize('not a doi'));
        $this->assertNull(Crossref::normalize('11.1000/xyz123'));
    }

    public function test_crossref_lookup_presents_the_fields_a_citation_needs(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response([
                'message' => [
                    'DOI' => '10.1000/xyz123',
                    'title' => ['A Real Paper'],
                    'container-title' => ['Journal of Things'],
                    'author' => [['given' => 'Ada', 'family' => 'Lovelace']],
                    'issued' => ['date-parts' => [[1843]]],
                    'publisher' => 'Taylor',
                    'type' => 'journal-article',
                ],
            ]),
        ]);

        $result = Crossref::lookup('https://doi.org/10.1000/xyz123');

        $this->assertSame('A Real Paper', $result['title']);
        $this->assertSame(['Ada Lovelace'], $result['authors']);
        $this->assertSame(1843, $result['year']);
    }

    public function test_a_manuscript_reads_its_author_from_the_persona(): void
    {
        [$user, $post] = $this->published();

        $manuscript = Manuscript::fromPost($post->load(['persona', 'user', 'categories']), $user);

        $this->assertSame('Ada Lovelace', $manuscript->authorName);
        $this->assertStringContainsString('on-the-analytical-engine', $manuscript->url);
    }
}
