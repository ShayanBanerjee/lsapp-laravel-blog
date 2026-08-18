<?php

namespace Tests\Unit;

use App\Support\Moderation\LexiconModerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The filter's job is narrow, and the false-positive tests matter more than the
 * true-positive ones. A platform for writing that silences strong disagreement
 * has broken its own proposition.
 */
class ModerationTest extends TestCase
{
    private LexiconModerator $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderator = new LexiconModerator;
    }

    /** @return array<string, array{string}> */
    public static function protectedSpeech(): array
    {
        return [
            'blunt disagreement' => ['This argument is completely wrong and you have not read the source.'],
            'profanity as emphasis' => ['This is a fucking brilliant piece, genuinely.'],
            'harsh criticism' => ['Honestly this is lazy thinking and the conclusion does not follow at all.'],
            'anger at the author' => ['I am furious that you published this. It is irresponsible.'],
            'political opinion' => ['This policy is authoritarian nonsense and its supporters are deluded.'],
            'discussing sexuality' => ['The essay handles sex and intimacy with unusual honesty.'],
            'discussing assault seriously' => ['She writes about surviving sexual assault, and it is devastating.'],
            'quoting a slur to condemn it' => ['My grandfather was called a slur every day and never spoke of it.'],
            'the scunthorpe class' => ['I grew up near Scunthorpe and moved to Penistone.'],
            'ordinary insult' => ['You are being an idiot about this.'],
        ];
    }

    #[DataProvider('protectedSpeech')]
    public function test_it_allows_protected_speech(string $text): void
    {
        $verdict = $this->moderator->check($text);

        $this->assertSame('allow', $verdict->action, "Wrongly flagged protected speech: {$text}");
    }

    public function test_it_blocks_directed_sexual_harassment(): void
    {
        foreach (['send me your nudes', 'i want to fuck you', 'suck my dick'] as $text) {
            $this->assertTrue($this->moderator->check($text)->isBlocked(), "Missed harassment: {$text}");
            $this->assertSame('sexual_harassment', $this->moderator->check($text)->category);
        }
    }

    public function test_it_blocks_slurs(): void
    {
        $verdict = $this->moderator->check('you absolute f4ggot');

        $this->assertTrue($verdict->isBlocked());
        $this->assertSame('slur', $verdict->category);
    }

    /** Cheap evasions should not defeat it. */
    public function test_it_sees_through_spacing_and_digit_substitution(): void
    {
        foreach (['n i g g e r', 'n.i.g.g.e.r', 'n1gger'] as $text) {
            $this->assertTrue($this->moderator->check($text)->isBlocked(), "Evasion succeeded: {$text}");
        }
    }

    /**
     * The critical distinction: sex as a *subject* is fine; sex *directed at a
     * person* is harassment.
     */
    public function test_it_distinguishes_subject_from_target(): void
    {
        $this->assertSame('allow', $this->moderator->check('The book is explicit about rape as a war crime.')->action);
        $this->assertTrue($this->moderator->check('i will rape you')->isBlocked());
    }
}
