<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Persona;
use App\Models\Post;
use App\Models\Scopes\StandalonePostScope;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One worked demonstration course.
 *
 * A learning path is the one feature that cannot be judged from an empty
 * state — a contents panel with nothing in it proves nothing about whether the
 * layout holds up at fifteen lessons across four modules. This seeds enough
 * structure to see that.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $persona = Persona::where('handle', 'longlight')->first() ?? Persona::first();

        if (! $persona) {
            return;
        }

        $course = Course::updateOrCreate(
            ['slug' => 'master-github-copilot-gh-300'],
            [
                'user_id' => $persona->user_id,
                'persona_id' => $persona->id,
                'universe_id' => $persona->universe_id,
                'title' => 'Master GitHub Copilot (GH-300)',
                'subtitle' => 'Everything on the exam, in the order it actually makes sense to learn it.',
                'description' => "A complete path through the GH-300 objectives, written to be read rather than skimmed.\n\nEach lesson stands on its own, so you can start where your gaps are. Mark any passage you want to come back to — the marks are yours, and the ones everyone marks tell me which explanations are working.",
                'level' => 'intermediate',
                'status' => 'published',
                'published_at' => now()->subWeeks(2),
            ]
        );

        foreach ($this->outline() as $moduleIndex => $moduleData) {
            $module = CourseModule::updateOrCreate(
                ['course_id' => $course->id, 'title' => $moduleData['title']],
                ['summary' => $moduleData['summary'], 'position' => $moduleIndex],
            );

            foreach ($moduleData['lessons'] as $lessonIndex => $lesson) {
                $body = collect($lesson['paragraphs'])
                    ->map(fn (string $paragraph) => '<p>'.e($paragraph).'</p>')
                    ->implode('');

                Post::withoutGlobalScope(StandalonePostScope::class)->updateOrCreate(
                    ['slug' => Str::slug($lesson['title'])],
                    [
                        'user_id' => $course->user_id,
                        'persona_id' => $course->persona_id,
                        'universe_id' => $course->universe_id,
                        'course_module_id' => $module->id,
                        'position' => $lessonIndex,
                        'title' => $lesson['title'],
                        'excerpt' => HtmlSanitizer::excerpt($body),
                        'body' => $body,
                        'status' => $course->status,
                        'reading_time' => Post::estimateReadingTime($body),
                        'published_at' => $course->published_at,
                    ]
                );
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function outline(): array
    {
        return [
            [
                'title' => 'What Copilot actually is',
                'summary' => 'The model, the context window, and why both matter before you type anything.',
                'lessons' => [
                    [
                        'title' => 'Completions, chat, and the difference that matters',
                        'paragraphs' => [
                            'Copilot presents as two products and is really one mechanism with two front doors. Inline completion watches the file you are in and proposes the next span of code. Chat takes a question and a selection and answers in prose. Both are the same underlying operation: assemble a context, ask a model to continue it.',
                            'Understanding that they share a mechanism is the difference between using Copilot and being used by it. Every technique in this course is ultimately about one thing — deciding what goes into the context, because that is the only lever you actually control.',
                            'The exam tests this distinction directly, usually by describing a scenario and asking which surface fits. The honest answer is almost always the one where the necessary context is already on screen.',
                        ],
                    ],
                    [
                        'title' => 'The context window, and what falls out of it',
                        'paragraphs' => [
                            'A context window is finite. Everything Copilot knows about your intent at the moment it answers came from the open file, the neighbouring tabs, and whatever you typed. Nothing else. Not the ticket, not the conversation you had yesterday, not the convention documented in a README nobody opened.',
                            'This explains most disappointing suggestions. The model is not ignoring your architecture; it has never seen it. The fix is rarely a better prompt and usually a better desk — the right files open, in the right order.',
                        ],
                    ],
                    [
                        'title' => 'Where the boundary sits: what Copilot never sees',
                        'paragraphs' => [
                            'Content exclusion, organisation policy and repository settings all subtract from what can reach the model. Knowing the subtraction rules is half the exam and most of the real-world debugging.',
                            'The practical consequence: when a colleague gets a good suggestion and you do not, the first question is not about prompting. It is about whether the same files are visible to both of you.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Prompting that survives review',
                'summary' => 'Getting output you would have written yourself, and being able to prove it.',
                'lessons' => [
                    [
                        'title' => 'Say the constraint, not the wish',
                        'paragraphs' => [
                            'The most reliable improvement to any prompt is replacing an adjective with a constraint. "Write a fast parser" is a wish. "Parse this without allocating per line" is a constraint, and constraints are the only part of a request a model can actually check its output against.',
                            'This is not a trick for talking to machines. It is the same discipline that makes a code review comment actionable rather than annoying.',
                        ],
                    ],
                    [
                        'title' => 'Writing the test first, on purpose',
                        'paragraphs' => [
                            'Handing Copilot a failing test is the highest-leverage prompt available, because it converts an ambiguous request into an executable specification. The model now has an oracle, and so do you.',
                            'It also inverts the trust problem. You are no longer reading generated code and asking "is this right?" — a question humans are measurably bad at answering. You are running it.',
                        ],
                    ],
                    [
                        'title' => 'Reviewing generated code without rubber-stamping it',
                        'paragraphs' => [
                            'The failure mode of assisted coding is not wrong code. It is plausible code that passes a glance. Fluency is not correctness, and generated code is fluent by construction.',
                            'Read generated code the way you would read a stranger\'s pull request: check the edge cases it did not mention, the error path it skipped, and the assumption it made about your data that you never stated.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Copilot in a real repository',
                'summary' => 'Everything changes when the codebase is large, old, and shared.',
                'lessons' => [
                    [
                        'title' => 'Custom instructions, and why they beat prompting',
                        'paragraphs' => [
                            'A repository-level instructions file moves your conventions from something you retype into something the context always carries. It is the difference between reminding a new colleague of the house style every morning and writing it down once.',
                            'Keep it short and specific. An instructions file that lists everything gets averaged into nothing.',
                        ],
                    ],
                    [
                        'title' => 'Working in code you did not write',
                        'paragraphs' => [
                            'Copilot is at its most useful on unfamiliar code, and at its most dangerous there too, because you have no prior to check its answer against.',
                            'The safe pattern is to ask it to explain before asking it to change. An explanation you can verify against the code is cheap; a change you cannot verify is expensive.',
                        ],
                    ],
                    [
                        'title' => 'Licensing, attribution and the duplication filter',
                        'paragraphs' => [
                            'The duplication detection filter exists because models can reproduce training data. Knowing what it does — and what it does not cover — is an exam objective and a professional obligation.',
                            'The short version: it blocks long verbatim matches against public code. It is not a licence audit, and treating it as one is how attribution problems reach production.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Sitting the exam',
                'summary' => 'What GH-300 asks, how it asks it, and where people lose marks.',
                'lessons' => [
                    [
                        'title' => 'The objective map, honestly weighted',
                        'paragraphs' => [
                            'Not all objectives carry equal weight, and the published outline does not say so. Responsible AI and privacy fundamentals are worth more than their line count suggests; plugin trivia is worth less.',
                            'Study to the weighting, not to the list.',
                        ],
                    ],
                    [
                        'title' => 'Scenario questions, and the trap in them',
                        'paragraphs' => [
                            'Most questions are scenarios with two defensible answers, one of which is defensible only if you ignore a constraint stated earlier in the stem. Read the stem twice before the options once.',
                            'When two answers look equally right, the differentiator is almost always a policy or privacy constraint mentioned in passing.',
                        ],
                    ],
                    [
                        'title' => 'The week before',
                        'paragraphs' => [
                            'Do not read new material in the last week. Re-read the sections you marked, and write out from memory the three subtractions that decide what Copilot can see.',
                            'If you can explain the context window to someone who has never used Copilot, you are ready.',
                        ],
                    ],
                ],
            ],
        ];
    }
}
