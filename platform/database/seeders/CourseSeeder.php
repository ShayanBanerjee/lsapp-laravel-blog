<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Universe;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One worked example of a learning path, so the side panel and progress
 * tracking have something real to render.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::updateOrCreate(
            ['email' => 'writer@example.com'],
            ['name' => 'Demo Writer', 'password' => Hash::make('password'), 'is_premium' => true, 'email_verified_at' => now()],
        );

        $universe = Universe::where('slug', 'cosmos')->firstOrFail();

        $persona = Persona::updateOrCreate(
            ['handle' => 'longlight'],
            ['user_id' => $author->id, 'universe_id' => $universe->id, 'display_name' => 'Long Light'],
        );

        foreach ($this->courses() as $data) {
            $course = Course::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'subtitle' => $data['subtitle'],
                    'description' => $data['description'],
                    'user_id' => $author->id,
                    'persona_id' => $persona->id,
                    'universe_id' => $universe->id,
                    'level' => $data['level'],
                    'status' => 'published',
                    'published_at' => now()->subDays(5),
                ],
            );

            foreach ($data['modules'] as $moduleIndex => $moduleData) {
                $module = CourseModule::updateOrCreate(
                    ['course_id' => $course->id, 'title' => $moduleData['title']],
                    ['summary' => $moduleData['summary'], 'sort_order' => $moduleIndex],
                );

                foreach ($moduleData['lessons'] as $lessonIndex => $lesson) {
                    $body = collect($lesson['paragraphs'])
                        ->map(fn (string $p) => '<p>'.e($p).'</p>')
                        ->implode('');

                    Post::updateOrCreate(
                        ['slug' => Str::slug($lesson['title'])],
                        [
                            'user_id' => $author->id,
                            'persona_id' => $persona->id,
                            'universe_id' => $universe->id,
                            'title' => $lesson['title'],
                            'body' => $body,
                            'excerpt' => HtmlSanitizer::excerpt($body, 160),
                            'status' => 'published',
                            'published_at' => now()->subDays(5),
                            'kind' => 'lesson',
                            'course_module_id' => $module->id,
                            'sort_order' => $lessonIndex,
                            'reading_time' => max(1, (int) ceil(str_word_count(strip_tags($body)) / 200)),
                        ],
                    );
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function courses(): array
    {
        return [
            [
                'slug' => 'writing-with-an-ai-pair',
                'title' => 'Writing With an AI Pair',
                'subtitle' => 'Getting useful work out of a coding assistant',
                'description' => 'A practical path through prompting, reviewing and knowing when to stop trusting the suggestion. Assumes you can already write the code yourself.',
                'level' => 'intermediate',
                'modules' => [
                    [
                        'title' => 'Setting expectations',
                        'summary' => 'What the tool is actually good at, and where it will confidently mislead you.',
                        'lessons' => [
                            [
                                'title' => 'The completion is a guess',
                                'paragraphs' => [
                                    'The single most useful mental adjustment is to stop reading a suggestion as an answer and start reading it as a guess with excellent grammar. It is drawn from what code usually looks like in this situation, which is a different thing from what your code should do in this situation.',
                                    'This is not a criticism of the tool. Usually-correct is enormously valuable when the alternative is typing the whole thing. It only becomes a problem when the confidence of the prose gets mistaken for the confidence of the claim.',
                                    'The habit worth building: before accepting, ask what would have to be true for this to be right. If you cannot answer, you are not reviewing, you are agreeing.',
                                ],
                            ],
                            [
                                'title' => 'Where it earns its keep',
                                'paragraphs' => [
                                    'Boilerplate with a known shape. Test scaffolding. The tedious middle of a function whose ends you have already written. Translating something you understand from one syntax into another. In all of these you already hold the correct answer and are only paying for the typing.',
                                    'The common thread is that you can check the output faster than you could have produced it. That ratio is the whole economic argument, and it collapses the moment you are asking about something you could not have verified yourself.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Prompting in practice',
                        'summary' => 'Concrete techniques, in rough order of how much they pay back.',
                        'lessons' => [
                            [
                                'title' => 'Give it the constraints, not the task',
                                'paragraphs' => [
                                    'A request phrased as a task invites the model to pick an approach. A request phrased as constraints leaves the approach to you and the typing to it. The second produces far less rework.',
                                    'In practice this means naming the types, the error behaviour and the thing you are optimising for, and saying nothing about how to get there.',
                                ],
                            ],
                            [
                                'title' => 'Show it the neighbours',
                                'paragraphs' => [
                                    'Code has house style, and a suggestion that ignores yours will be technically fine and still wrong for the file it lands in. Give the surrounding code before asking, and most of the mismatch disappears.',
                                    'This is why an assistant that reads the repository outperforms one working from the prompt alone — not because it is smarter, but because it has seen the neighbours.',
                                ],
                            ],
                            [
                                'title' => 'Stop when it starts agreeing',
                                'paragraphs' => [
                                    'A model that has been corrected twice will often start producing whatever it thinks you want, which is the least useful possible behaviour from a reviewer. The signal is agreement arriving faster than the reasoning behind it.',
                                    'When that happens, the productive move is to discard the thread and start again with better constraints, rather than to keep negotiating.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'title' => 'Reviewing what comes back',
                        'summary' => 'The part that is still entirely yours.',
                        'lessons' => [
                            [
                                'title' => 'Read the edges first',
                                'paragraphs' => [
                                    'Generated code is most reliable in the middle of a function and least reliable at its boundaries: the empty input, the error path, the concurrent call, the second invocation. Those are the places where usually-correct stops being good enough.',
                                    'Reviewing the happy path first is a comfortable habit and almost never finds anything. Start where the guessing is worst.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
