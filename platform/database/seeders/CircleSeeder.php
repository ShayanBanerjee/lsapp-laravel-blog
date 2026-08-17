<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\Prompt;
use App\Models\Universe;
use Illuminate\Database\Seeder;

/**
 * Circles gather people by subject. Prompts remove the blank page.
 */
class CircleSeeder extends Seeder
{
    public function run(): void
    {
        $universes = Universe::pluck('id', 'slug');

        $circles = [
            ['slug' => 'slow-noticing', 'name' => 'Slow Noticing', 'universe' => 'nature', 'sort_order' => 1,
                'tagline' => 'For people who stopped and looked at the thing.',
                'description' => 'Close attention to small subjects. Field notes, textures, the underside of leaves, and whatever you found when you finally stopped walking.'],
            ['slug' => 'the-long-view', 'name' => 'The Long View', 'universe' => 'cosmos', 'sort_order' => 2,
                'tagline' => 'Writing at a distance that makes things clear.',
                'description' => 'Deep time, deep space, and the useful vertigo of holding a small problem up against something enormous.'],
            ['slug' => 'plain-language', 'name' => 'Plain Language', 'universe' => 'desert', 'sort_order' => 3,
                'tagline' => 'Everything unnecessary burned off.',
                'description' => 'A circle for people trying to say exactly what they mean with nothing left over. Bring your shortest draft.'],
            ['slug' => 'first-drafts', 'name' => 'First Drafts', 'universe' => null, 'sort_order' => 4,
                'tagline' => 'Unfinished on purpose.',
                'description' => 'Post the thing before it is ready. No one here is allowed to say "this needs work" without saying which part and why.'],
            ['slug' => 'night-shift', 'name' => 'Night Shift', 'universe' => 'abyss', 'sort_order' => 5,
                'tagline' => 'Written after everyone else went to bed.',
                'description' => 'The pieces that only get written at 2am, and the particular honesty that shows up when nobody is expected to be reading.'],
            ['slug' => 'overgrown', 'name' => 'Overgrown', 'universe' => 'jungle', 'sort_order' => 6,
                'tagline' => 'Too much, on purpose.',
                'description' => 'Maximalists welcome. Long sentences, dense paragraphs, and arguments that refuse to be summarised in a subtitle.'],
        ];

        foreach ($circles as $circle) {
            $universeSlug = $circle['universe'];
            unset($circle['universe']);

            Circle::updateOrCreate(
                ['slug' => $circle['slug']],
                [...$circle, 'universe_id' => $universeSlug ? $universes[$universeSlug] ?? null : null],
            );
        }

        $prompts = [
            'cosmos' => [
                'Write about something you can only see clearly from very far away.',
                'What is the longest span of time you have ever genuinely felt?',
                'Describe a thing that is still happening but has already finished.',
            ],
            'nature' => [
                'Write about something that got bigger as you got closer to it.',
                'Describe a process that took a year and that nobody watched.',
                'What is growing somewhere you would rather it did not?',
            ],
            'mountains' => [
                'Write the shortest true version of something you usually explain at length.',
                'Describe a place that was indifferent to you, and what that taught you.',
                'What did you have to put down in order to keep going up?',
            ],
            'jungle' => [
                'Write about competing for something that was never going to be shared.',
                'Describe a place where you could hear more than you could see.',
                'What is thriving in your life specifically because you neglected it?',
            ],
            'abyss' => [
                'Write about something you made yourself because nobody was going to provide it.',
                'Describe the pressure of a place nobody else has been.',
                'What did you find at the point where you stopped being able to see?',
            ],
            'desert' => [
                'Write something with every adjective removed. Then put back only the ones you miss.',
                'Describe scarcity without once using the word.',
                'What did the emptiness turn out to contain?',
            ],
        ];

        foreach ($prompts as $slug => $lines) {
            if (! isset($universes[$slug])) {
                continue;
            }

            foreach ($lines as $line) {
                Prompt::firstOrCreate(['universe_id' => $universes[$slug], 'body' => $line]);
            }
        }
    }
}
