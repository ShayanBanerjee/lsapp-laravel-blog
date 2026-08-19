<?php

namespace Tests\Unit;

use App\Support\Copy\Fingerprint;
use PHPUnit\Framework\TestCase;

/**
 * The properties that make SimHash usable for this at all: it must move a
 * little for a small edit and a lot for a different document. A cryptographic
 * hash fails the first half, which is why one is not used here.
 */
class FingerprintTest extends TestCase
{
    private function passage(): string
    {
        return 'The tide goes out further here than anywhere else on this coast, and what it leaves behind is a landscape '
            .'that exists for six hours at a time. Channels that will be a river by evening are footpaths at noon. '
            .'People who live here read the water the way other people read a timetable, and they are never wrong twice.';
    }

    public function test_normalisation_ignores_markup_case_and_punctuation(): void
    {
        $a = Fingerprint::normalize('<p>The <strong>Tide</strong>, goes out — further!</p>');
        $b = Fingerprint::normalize('the tide goes out further');

        $this->assertSame($b, $a);
    }

    public function test_shingles_are_overlapping_word_windows(): void
    {
        $shingles = Fingerprint::shingles('one two three four five six', 5);

        $this->assertSame(['one two three four five', 'two three four five six'], $shingles);
    }

    public function test_text_shorter_than_the_window_produces_no_shingles(): void
    {
        $this->assertSame([], Fingerprint::shingles('too short', 5));
    }

    public function test_an_identical_document_has_an_identical_simhash(): void
    {
        $a = Fingerprint::simhash(Fingerprint::shingles($this->passage()));
        $b = Fingerprint::simhash(Fingerprint::shingles($this->passage()));

        $this->assertSame($a, $b);
        $this->assertSame(0, Fingerprint::hamming($a, $b));
    }

    public function test_a_small_edit_moves_the_simhash_only_a_little(): void
    {
        $original = Fingerprint::simhash(Fingerprint::shingles($this->passage()));
        $edited = Fingerprint::simhash(Fingerprint::shingles(
            str_replace('never wrong twice', 'never wrong more than once', $this->passage())
        ));

        $distance = Fingerprint::hamming($original, $edited);

        $this->assertGreaterThan(0, $distance, 'an edit should be visible at all');
        $this->assertLessThanOrEqual(12, $distance, "a small edit moved the hash by {$distance} bits");
    }

    public function test_an_unrelated_document_is_far_away(): void
    {
        $mine = Fingerprint::simhash(Fingerprint::shingles($this->passage()));
        $theirs = Fingerprint::simhash(Fingerprint::shingles(
            'Compilers spend most of their time in the middle end, and most of the interesting decisions are made there. '
            .'Register allocation is the part everyone remembers, but instruction selection is where the real losses are. '
            .'A good pass ordering will beat a clever individual pass almost every time.'
        ));

        $this->assertGreaterThan(18, Fingerprint::hamming($mine, $theirs));
    }

    public function test_the_sample_is_consistent_between_documents_sharing_a_passage(): void
    {
        // The property that makes sampling work at all: selection is on the
        // hash, so the same passage picks the same shingles in both documents
        // even though the surrounding text differs.
        $shared = $this->passage();

        $one = Fingerprint::sample(Fingerprint::shingles('An opening nobody else wrote. '.$shared));
        $two = Fingerprint::sample(Fingerprint::shingles($shared.' A closing nobody else wrote either.'));

        $overlap = count(array_intersect($one, $two));

        $this->assertGreaterThan(4, $overlap, 'a shared passage should select shared shingles');
    }

    public function test_sampling_keeps_roughly_the_expected_fraction(): void
    {
        $shingles = Fingerprint::shingles(str_repeat($this->passage().' ', 8));
        $sample = Fingerprint::sample($shingles);

        $ratio = count($sample) / max(1, count(array_unique($shingles)));

        // Sampling is by hash value, so the exact count varies; what matters is
        // that it is a fraction rather than everything.
        $this->assertGreaterThan(0.1, $ratio);
        $this->assertLessThan(0.6, $ratio);
    }

    public function test_containment_is_the_share_of_your_own_material_found_elsewhere(): void
    {
        $this->assertSame(0.5, Fingerprint::containment(10, 20));
        $this->assertSame(0.0, Fingerprint::containment(0, 20));
        $this->assertSame(0.0, Fingerprint::containment(5, 0));
    }

    public function test_an_empty_document_hashes_without_erroring(): void
    {
        $this->assertSame(str_repeat('0', 16), Fingerprint::simhash([]));
    }
}
