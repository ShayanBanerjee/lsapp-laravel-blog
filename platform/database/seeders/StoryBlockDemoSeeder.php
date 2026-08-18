<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;

/** Puts one of each storytelling block into a demo piece, so they are visible. */
class StoryBlockDemoSeeder extends Seeder
{
    public function run(): void
    {
        $post = Post::where('slug', 'everything-you-see-is-already-over')->first();

        if (! $post || str_contains($post->body, 'data-story=')) {
            return;
        }

        $blocks = '<figure data-story="callout" data-story-value="4.2" data-story-label="light years to the nearest star">'
            .'<p>Far enough that the light on your face tonight left before you had the thought that made you look up.</p></figure>'
            .'<figure data-story="steps"><p>Light leaves the surface of the star.</p>'
            .'<p>It crosses four years of nothing at all.</p>'
            .'<p>It lands on a retina that did not exist when it set out.</p></figure>'
            .'<figure data-story="pinned"><img src="/images/covers/cosmos-1.jpg" alt="A field of stars">'
            .'<p>The image holds still while this column moves past it, which is the whole trick — you control the descent, not a timeline.</p>'
            .'<p>Stop as long as you like. Go back up. A video of the same content would be someone else pacing you.</p></figure>';

        $post->update(['body' => HtmlSanitizer::clean($post->body.$blocks)]);
    }
}
