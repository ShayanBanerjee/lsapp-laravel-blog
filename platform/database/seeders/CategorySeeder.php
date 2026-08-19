<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Subject taxonomy, orthogonal to universes.
 *
 * A universe is *how a piece feels*; a subject is *what it is about*. Keeping
 * them separate means a technical essay can be written in Abyss without the
 * taxonomy fighting the mood.
 *
 * The copy here is doing real work. A subject hub that says "Technology:
 * articles about technology" tells a writer nothing about whether their piece
 * belongs, so each of these states what is wanted *and what is not* — an
 * editor's job, done once, in advance. The prompt is the other half: the
 * commonest reason a subject stays empty is not that nobody has anything to
 * say, it is that nobody knows where to start.
 *
 * Imagery is drawn from the bundled Unsplash-licensed set. Nothing here is
 * fetched from the open web — see ../../CLAUDE.md on why that matters.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::categories() as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function categories(): array
    {
        return [
            [
                'slug' => 'technology',
                'name' => 'Technology',
                'tagline' => 'The machines and their makers',
                'description' => 'Code, hardware, infrastructure, and the decisions buried inside them. Post-mortems, teardowns, and arguments about trade-offs belong here. Product launch summaries and funding-round news do not. Assume your reader is clever and impatient.',
                'prompt' => 'Write about a tool you use daily and what its design quietly assumes about you.',
                'hero_image' => '/images/covers/cosmos-1.jpg',
                'icon' => 'Cpu',
                'sort_order' => 1,
            ],
            [
                'slug' => 'science',
                'name' => 'Science',
                'tagline' => 'How we know what we know',
                'description' => 'Findings, methods, and the messy business of getting them. We want the reasoning shown, the uncertainty stated, the dead ends included. Press-release paraphrase and certainty you have not earned belong elsewhere.',
                'prompt' => 'Explain a result you find beautiful, then say honestly what would change your mind about it.',
                'hero_image' => '/images/covers/cosmos-2.jpg',
                'icon' => 'Atom',
                'sort_order' => 2,
            ],
            [
                'slug' => 'philosophy',
                'name' => 'Philosophy',
                'tagline' => 'Old questions, still open',
                'description' => 'Arguments you can follow and disagree with, held to ordinary language. Ethics, mind, meaning, politics of the everyday. Not vague uplift, not seminar jargon, and no thinker quoted as a substitute for thinking.',
                'prompt' => 'Take a belief you hold firmly and build the strongest case against it you can.',
                'hero_image' => '/images/covers/mountains-1.jpg',
                'icon' => 'BrainCircuit',
                'sort_order' => 3,
            ],
            [
                'slug' => 'nature-environment',
                'name' => 'Nature',
                'tagline' => 'Attention paid to living things',
                'description' => 'Weather, animals, plants, soil, and the places you keep going back to. Close observation beats sweeping awe here — one hedgerow watched for a year is worth more than a continent skimmed.',
                'prompt' => 'Describe one square metre of ground near you, and what has changed there since spring.',
                'hero_image' => '/images/covers/nature-1.jpg',
                'icon' => 'Leaf',
                'sort_order' => 4,
            ],
            [
                'slug' => 'history',
                'name' => 'History',
                'tagline' => 'The past, argued over',
                'description' => "Periods, people, objects, and the sources that survive them. Show your evidence and say who disagrees. We'd rather have one well-sourced decade than a confident sweep across a thousand years.",
                'prompt' => 'Pick a year that mattered to one street, one family, or one trade, and reconstruct it.',
                'hero_image' => '/images/covers/desert-1.jpg',
                'icon' => 'Landmark',
                'sort_order' => 5,
            ],
            [
                'slug' => 'culture-arts',
                'name' => 'Culture',
                'tagline' => 'Criticism with something at stake',
                'description' => 'Books, film, music, games, internet ephemera — and the criticism that treats them as worth arguing about. Star ratings and recap summaries are thin fare; tell us what the work is doing and why it lands.',
                'prompt' => 'Write about something popular you were wrong about, and what changed on the second look.',
                'hero_image' => '/images/covers/jungle-1.jpg',
                'icon' => 'Palette',
                'sort_order' => 6,
            ],
            [
                'slug' => 'personal-essay',
                'name' => 'The Personal',
                'tagline' => 'Your life, told straight',
                'description' => 'Memoir, letters, grief, work, family, the ordinary Tuesday. The test is not how much you reveal but how well you see it. Confession without craft wears thin; so does a lesson tacked on at the end.',
                'prompt' => 'Write about a room you no longer have access to, in as much detail as you remember.',
                'hero_image' => '/images/covers/nature-2.jpg',
                'icon' => 'Heart',
                'sort_order' => 7,
            ],
            [
                'slug' => 'craft-writing',
                'name' => 'Craft',
                'tagline' => 'How the thing gets made',
                'description' => 'Making, and the practice behind it — writing, building, cooking, repair, any skill with hours in it. We want method, failure, and the fiddly bits. Finished photographs with no process attached go nowhere.',
                'prompt' => 'Describe the mistake you made most often when learning your craft, and what finally fixed it.',
                'hero_image' => '/images/covers/mountains-2.jpg',
                'icon' => 'PenTool',
                'sort_order' => 8,
            ],
            [
                'slug' => 'health',
                'name' => 'Health',
                'tagline' => 'Bodies, minds, and the evidence',
                'description' => 'Illness, recovery, medicine, sleep, sport, caring for people. First-hand accounts are welcome; prescriptions for strangers are not. Cite what you can, say what you cannot, and leave the miracle protocols at the door.',
                'prompt' => 'Write about a change to your body or mind you noticed before anyone thought to name it.',
                'hero_image' => '/images/covers/nature-3.jpg',
                'icon' => 'Activity',
                'sort_order' => 9,
            ],
            [
                'slug' => 'futures',
                'name' => 'Futures',
                'tagline' => 'What comes next, argued carefully',
                'description' => 'Forecasts, scenarios, speculation with its workings shown. Say what would have to be true, and by when. Confident predictions with no mechanism, and dread dressed up as analysis, are easy to write and dull to read.',
                'prompt' => 'Describe an ordinary weekday in 2050 for someone doing your job, then defend each detail.',
                'hero_image' => '/images/covers/abyss-1.jpg',
                'icon' => 'Telescope',
                'sort_order' => 10,
            ],
            [
                'slug' => 'travel',
                'name' => 'Travel',
                'tagline' => 'Somewhere else, precisely',
                'description' => 'Places, and what being in them does to you. The specific street, the particular hour, the thing you got wrong about it before you arrived. Itineraries and listicles are for somewhere with an affiliate scheme.',
                'prompt' => 'Write about the first hour after arriving somewhere you had only read about.',
                'hero_image' => '/images/covers/desert-2.jpg',
                'icon' => 'Compass',
                'sort_order' => 11,
            ],
            [
                'slug' => 'tutorials',
                'name' => 'Tutorials',
                'tagline' => 'Written to be followed',
                'description' => 'Step-by-step, tested end to end, with the failure modes named. If you have not run it yourself from a clean state, it is not ready. The best ones say what to do when it does not work.',
                'prompt' => 'Teach the thing you had to work out yourself because nobody had written it down.',
                'hero_image' => '/images/covers/cosmos-3.jpg',
                'icon' => 'GraduationCap',
                'sort_order' => 12,
            ],
        ];
    }
}
