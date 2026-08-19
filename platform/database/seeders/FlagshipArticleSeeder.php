<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Circle;
use App\Models\Highlight;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The essays a first-time visitor actually reads.
 *
 * Demo content is usually the weakest part of a platform and the first thing
 * anyone sees, which is the wrong way round. These are real essays with real
 * arguments, and they are here to do three specific jobs: show what the reading
 * view looks like carrying prose worth reading, give the marking layer
 * something to mark, and let a subject hub open with a sentence somebody
 * stopped on rather than an empty state.
 *
 * The bodies live in `content/` as plain paragraph arrays so the prose is
 * editable without touching seeder logic.
 */
class FlagshipArticleSeeder extends Seeder
{
    /** @var array<int, string> */
    private const ESSAYS = ['estimates', 'indexes', 'dependencies', 'latency'];

    public function run(): void
    {
        $author = $this->author();
        $persona = $this->persona($author);

        foreach (self::ESSAYS as $index => $file) {
            $essay = require database_path("seeders/content/{$file}.php");

            $post = $this->publish($author, $persona, $essay, $index);

            $this->categorise($post, $essay['category']);
            $this->addToCircle($post);
            $this->markThePullQuote($post, $essay['pull_quote']);
        }
    }

    private function author(): User
    {
        return User::updateOrCreate(
            ['email' => 'field-notes@example.com'],
            [
                'name' => 'Field Notes',
                'password' => Hash::make('password'),
                'is_premium' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    private function persona(User $author): Persona
    {
        // Signal: the machine room. These essays are about the systems we
        // built to think with, which is exactly what that world is for.
        $universe = Universe::where('slug', 'signal')->firstOrFail();

        return Persona::updateOrCreate(
            ['handle' => 'fieldnotes'],
            [
                'user_id' => $author->id,
                'universe_id' => $universe->id,
                'display_name' => 'Field Notes',
                'bio' => 'Essays on how software actually behaves, as opposed to how it is described in the documentation.',
            ]
        );
    }

    /** @param  array<string, mixed>  $essay */
    private function publish(User $author, Persona $persona, array $essay, int $index): Post
    {
        $body = collect($essay['paragraphs'])
            ->map(fn (string $paragraph) => '<p>'.e($paragraph).'</p>')
            ->implode('');

        return Post::updateOrCreate(
            ['slug' => Str::slug($essay['title'])],
            [
                'user_id' => $author->id,
                'persona_id' => $persona->id,
                'universe_id' => $persona->universe_id,
                'title' => $essay['title'],
                'excerpt' => HtmlSanitizer::excerpt($essay['excerpt'], 200),
                'body' => HtmlSanitizer::clean($body),
                'cover_image' => '/images/covers/signal-'.(($index % 3) + 1).'.svg',
                'status' => 'published',
                'reading_time' => Post::estimateReadingTime($body),
                // Staggered so the feed has a plausible publication history
                // rather than four pieces sharing one timestamp.
                'published_at' => now()->subDays(3 + $index * 5),
            ]
        );
    }

    private function categorise(Post $post, string $slug): void
    {
        $category = Category::where('slug', $slug)->first();

        if ($category) {
            $post->categories()->syncWithoutDetaching([$category->id]);
        }
    }

    private function addToCircle(Post $post): void
    {
        $circle = Circle::query()
            ->where('slug', 'like', '%engineer%')
            ->orWhere('slug', 'like', '%tech%')
            ->orWhere('slug', 'like', '%craft%')
            ->first() ?? Circle::first();

        if ($circle) {
            $post->circles()->syncWithoutDetaching([$circle->id]);
        }
    }

    /**
     * Mark the essay's best line, as several readers.
     *
     * Without this the reading view, the subject hubs and the circle pages all
     * open in their empty state, and the one feature the whole platform is
     * built around is invisible until someone happens to select some text.
     *
     * Anchoring uses the same (block index, start, end) scheme the real
     * marking layer uses, resolved against the rendered paragraphs — a
     * hand-written offset would drift the moment the prose was edited.
     */
    private function markThePullQuote(Post $post, string $quote): void
    {
        preg_match_all('#<p>(.*?)</p>#s', $post->body, $matches);

        foreach ($matches[1] as $blockIndex => $paragraph) {
            $plain = html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_HTML5);

            // Case-insensitive: a pull quote is usually written with a capital
            // first letter even when the sentence it came from continues after
            // a colon, and an exact match would silently find nothing.
            $offset = mb_stripos($plain, $quote);

            if ($offset === false) {
                continue;
            }

            // Store the passage exactly as it reads in the piece, not as the
            // pull quote was capitalised.
            $quote = mb_substr($plain, $offset, mb_strlen($quote));

            $readers = User::where('email', '!=', $post->user->email)
                ->limit(4)
                ->get();

            foreach ($readers as $reader) {
                Highlight::firstOrCreate(
                    [
                        'post_id' => $post->id,
                        'user_id' => $reader->id,
                        'block_index' => $blockIndex,
                        'start_offset' => $offset,
                        'end_offset' => $offset + mb_strlen($quote),
                    ],
                    ['quote' => $quote],
                );
            }

            return;
        }
    }
}
