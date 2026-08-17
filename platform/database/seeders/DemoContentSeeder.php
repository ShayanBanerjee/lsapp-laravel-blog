<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::updateOrCreate(
            ['email' => 'writer@example.com'],
            [
                'name' => 'Demo Writer',
                'password' => Hash::make('password'),
                'is_premium' => true,
                'email_verified_at' => now(),
            ]
        );

        foreach ($this->seedData() as $slug => $data) {
            $universe = Universe::where('slug', $slug)->firstOrFail();

            $persona = Persona::updateOrCreate(
                ['handle' => $data['handle']],
                [
                    'user_id' => $demo->id,
                    'universe_id' => $universe->id,
                    'display_name' => $data['display_name'],
                    'bio' => $data['bio'],
                ]
            );

            foreach ($data['posts'] as $index => $post) {
                $body = collect($post['paragraphs'])
                    ->map(fn (string $paragraph) => '<p>'.e($paragraph).'</p>')
                    ->implode('');

                Post::updateOrCreate(
                    ['slug' => Str::slug($post['title'])],
                    [
                        'user_id' => $demo->id,
                        'persona_id' => $persona->id,
                        'universe_id' => $universe->id,
                        'title' => $post['title'],
                        'excerpt' => HtmlSanitizer::excerpt($body),
                        'body' => $body,
                        // Bundled Unsplash photography, three per universe.
                        // Stored as an absolute public path rather than a
                        // storage-disk path, so PostController::card() must not
                        // run it through Storage::url().
                        'cover_image' => sprintf('/images/covers/%s-%d.jpg', $slug, ($index % 3) + 1),
                        'status' => 'published',
                        'reading_time' => Post::estimateReadingTime($body),
                        'published_at' => now()->subDays(($index + 1) * 3 + random_int(0, 2)),
                    ]
                );
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function seedData(): array
    {
        return [
            'cosmos' => [
                'handle' => 'longlight',
                'display_name' => 'Long Light',
                'bio' => 'Writing at the speed of arriving photons. Mostly about distance.',
                'posts' => [
                    [
                        'title' => 'Everything You See Is Already Over',
                        'paragraphs' => [
                            'The nearest star other than our own is four light years out, which means the light landing on your face tonight left before you had the thought that made you look up.',
                            'There is no way to see the present sky. There is only a composite of moments, each one arriving late, assembled into something that looks like a single instant because our eyes are too slow to argue.',
                            'I find this consoling rather than bleak. Everything is a record. Nothing that ever happened has finished travelling.',
                        ],
                    ],
                    [
                        'title' => 'On the Usefulness of Being Small',
                        'paragraphs' => [
                            'Scale is the only honest editor. Hold a problem up against a galaxy and most of it burns off, and what survives that is worth writing down.',
                            'This is not an argument that nothing matters. It is an argument that very little does, and that the little is unusually precise.',
                        ],
                    ],
                ],
            ],
            'nature' => [
                'handle' => 'underloam',
                'display_name' => 'Under Loam',
                'bio' => 'Field notes from ground level. Slow, damp, specific.',
                'posts' => [
                    [
                        'title' => 'The Year the Moss Took the North Wall',
                        'paragraphs' => [
                            'It began as a discolouration you could mistake for damp, and by autumn it had the confidence of something that intended to stay.',
                            'Moss does not race. It occupies. There is a difference, and the difference is patience, which is the only strategy that works on stone.',
                            'I have stopped scraping it off. The wall is not losing.',
                        ],
                    ],
                    [
                        'title' => 'Nine Things Living in a Handful of Soil',
                        'paragraphs' => [
                            'Fungal thread, root hair, springtail, mite, nematode, bacterium, a fragment of last year’s leaf, water held in the gaps, and the air that the gaps make possible.',
                            'Every one of them is doing something to the others. None of them are aware of the arrangement. It works anyway, which is the part I keep returning to.',
                        ],
                    ],
                ],
            ],
            'mountains' => [
                'handle' => 'coldface',
                'display_name' => 'Cold Face',
                'bio' => 'Above the treeline. Short sentences, thin air.',
                'posts' => [
                    [
                        'title' => 'What the Wind Removes',
                        'paragraphs' => [
                            'At altitude, everything unnecessary gets taken off you. Layers first, then opinions.',
                            'The mountain is indifferent, and indifference at that scale reads almost as generosity. It is not trying to teach you anything. That is precisely why you learn.',
                        ],
                    ],
                ],
            ],
            'jungle' => [
                'handle' => 'canopywire',
                'display_name' => 'Canopy Wire',
                'bio' => 'Dispatches from the loud green dark.',
                'posts' => [
                    [
                        'title' => 'Competition for Light, Told as a Vertical Story',
                        'paragraphs' => [
                            'The forest is not a place, it is a queue. Everything is standing on something else’s shoulders trying to reach the same ceiling.',
                            'At the floor it is nearly night at noon. What grows there has given up on the sun entirely and made other arrangements.',
                        ],
                    ],
                ],
            ],
            'abyss' => [
                'handle' => 'pressuredepth',
                'display_name' => 'Pressure Depth',
                'bio' => 'Below the last of the light.',
                'posts' => [
                    [
                        'title' => 'Creatures That Invented Their Own Daylight',
                        'paragraphs' => [
                            'When the sun stopped being an option, a great many animals independently decided to become the light source themselves.',
                            'Bioluminescence has evolved dozens of separate times down here. That is not coincidence. That is a problem with one obvious answer, found over and over by things that never met.',
                        ],
                    ],
                ],
            ],
            'desert' => [
                'handle' => 'saltflat',
                'display_name' => 'Salt Flat',
                'bio' => 'Writing with the adjectives burned off.',
                'posts' => [
                    [
                        'title' => 'A Hundred Miles of Nothing to Hide Behind',
                        'paragraphs' => [
                            'The desert does not permit vagueness. There is no scenery to gesture at, so you have to actually say what you mean.',
                            'Water becomes the only subject. Everything else is a footnote to where the water is and how long you have before it matters.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
