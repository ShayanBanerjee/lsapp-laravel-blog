<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\Academic\Manuscript;
use App\Support\Academic\SubmissionPackage;
use App\Support\Academic\Venues\VenueRegistry;
use Database\Seeders\UniverseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ResearchStudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UniverseSeeder::class);
    }

    /** @return array{0: User, 1: Post} */
    private function paper(bool $premium = true): array
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace', 'is_premium' => $premium]);
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
            'body' => '<h2>Method</h2><p>It weaves algebraic patterns.</p><ul><li>First</li></ul>',
            'status' => 'published',
            'reading_time' => 6,
            'published_at' => now()->subDay(),
        ]);

        return [$user, $post];
    }

    public function test_every_venue_declares_a_complete_contract(): void
    {
        $registry = new VenueRegistry;

        $this->assertGreaterThanOrEqual(5, $registry->all()->count());

        $registry->all()->each(function ($venue) {
            $this->assertNotSame('', $venue->name());
            $this->assertNotSame('', $venue->publisher());
            $this->assertNotEmpty($venue->checklist(), $venue->key().' has no checklist');

            // Both links must be real https URLs — these are the things a
            // researcher actually clicks through to.
            $this->assertStringStartsWith('https://', $venue->guidelinesUrl());
            $this->assertStringStartsWith('https://', $venue->portalUrl());
        });
    }

    public function test_each_venue_renders_its_own_document_class(): void
    {
        [, $post] = $this->paper();
        $manuscript = Manuscript::fromPost($post->load(['persona', 'user', 'categories']));

        $expected = [
            'ieee' => '\documentclass[conference]{IEEEtran}',
            'acm' => '\documentclass[sigconf]{acmart}',
            'springer' => '{sn-jnl}',
            'nature' => '\documentclass[11pt,a4paper]{article}',
            'arxiv' => '\documentclass[11pt,a4paper]{article}',
        ];

        $registry = new VenueRegistry;

        foreach ($expected as $key => $marker) {
            $latex = $registry->find($key)->renderLatex($manuscript);

            $this->assertStringContainsString($marker, $latex, "{$key} did not use its own class");
            // Special characters must survive into every venue's output.
            $this->assertStringContainsString('\& 100\%', $latex, "{$key} did not escape the title");
            $this->assertStringContainsString('\end{document}', $latex);
        }
    }

    public function test_nature_asks_for_double_spacing_and_line_numbers(): void
    {
        [, $post] = $this->paper();
        $latex = (new VenueRegistry)->find('nature')
            ->renderLatex(Manuscript::fromPost($post->load(['persona', 'user', 'categories'])));

        $this->assertStringContainsString('\doublespacing', $latex);
        $this->assertStringContainsString('\linenumbers', $latex);
    }

    public function test_the_package_contains_everything_a_portal_asks_for(): void
    {
        [, $post] = $this->paper();
        $manuscript = Manuscript::fromPost($post->load(['persona', 'user', 'categories']));

        $archive = SubmissionPackage::build((new VenueRegistry)->find('ieee'), $manuscript);

        $file = tempnam(sys_get_temp_dir(), 'pkg-test-');
        file_put_contents($file, $archive);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file) === true);

        foreach (['README.md', 'manuscript.tex', 'manuscript.docx', 'references.bib', 'metadata.json', 'cover-letter.md', 'CHECKLIST.md'] as $entry) {
            $this->assertNotFalse($zip->locateName($entry), "package is missing {$entry}");
        }

        $metadata = json_decode($zip->getFromName('metadata.json'), true);
        $readme = $zip->getFromName('README.md');
        $zip->close();
        unlink($file);

        $this->assertSame('IEEE', $metadata['venue']['name']);
        $this->assertSame('On the Analytical Engine & 100% of its uses', $metadata['manuscript']['title']);
        // The email is left null rather than invented — a portal rejects a
        // wrong corresponding author, and a guess is worse than a gap.
        $this->assertNull($metadata['authors'][0]['email']);

        // The README must say plainly that nothing was submitted.
        $this->assertStringContainsString('has not been submitted anywhere', $readme);
    }

    public function test_the_studio_is_readable_by_any_author_but_packages_are_premium(): void
    {
        [$free, $post] = $this->paper(premium: false);

        // The page itself is open, so the free tier can see what it does.
        $this->actingAs($free)->get("/posts/{$post->slug}/studio")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('studio')->where('canPrepare', false));

        // The package is the paid capability, enforced server-side.
        $this->actingAs($free)->get("/posts/{$post->slug}/studio/ieee")->assertForbidden();

        $free->update(['is_premium' => true]);
        $this->actingAs($free)->get("/posts/{$post->slug}/studio/ieee")->assertOk();
    }

    public function test_only_the_author_can_open_the_studio(): void
    {
        [, $post] = $this->paper();
        $stranger = User::factory()->create(['is_premium' => true]);

        $this->actingAs($stranger)->get("/posts/{$post->slug}/studio")->assertForbidden();
        $this->actingAs($stranger)->get("/posts/{$post->slug}/studio/ieee")->assertForbidden();
    }

    public function test_an_unknown_venue_is_a_404(): void
    {
        [$user, $post] = $this->paper();

        $this->actingAs($user)->get("/posts/{$post->slug}/studio/elsevier-maybe")->assertNotFound();
    }

    public function test_readiness_names_specific_gaps_rather_than_scoring(): void
    {
        [$user, $post] = $this->paper();

        $this->actingAs($user)->get("/posts/{$post->slug}/studio")
            ->assertInertia(function ($page) {
                $readiness = collect($page->toArray()['props']['readiness']);

                $orcid = $readiness->firstWhere('label', 'ORCID linked');
                $this->assertFalse($orcid['ok']);
                $this->assertNotSame('', $orcid['hint']);

                $abstract = $readiness->firstWhere('label', 'Abstract written');
                $this->assertTrue($abstract['ok']);
            });
    }
}
