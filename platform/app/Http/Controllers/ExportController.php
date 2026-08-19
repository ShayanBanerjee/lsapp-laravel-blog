<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Academic\BibtexExporter;
use App\Support\Academic\DocxExporter;
use App\Support\Academic\LatexExporter;
use App\Support\Academic\Manuscript;
use App\Support\Academic\MarkdownExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Getting a piece out of the platform.
 *
 * Export is deliberately generous about who may do it. Reading is public here,
 * so a reader taking a published piece into their own notes — Markdown for a
 * vault, BibTeX for a bibliography — is taking something they already have.
 * Manuscript formats intended for submission (LaTeX, DOCX) are the author's
 * own, and are restricted to them.
 */
class ExportController extends Controller
{
    /** format => [extension, mime, authorOnly] */
    private const FORMATS = [
        'markdown' => ['md', 'text/markdown; charset=UTF-8', false],
        'bibtex' => ['bib', 'application/x-bibtex; charset=UTF-8', false],
        'latex' => ['tex', 'application/x-tex; charset=UTF-8', true],
        'docx' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', true],
    ];

    public function show(Request $request, Post $post, string $format): StreamedResponse
    {
        abort_unless(isset(self::FORMATS[$format]), 404);

        [$extension, $mime, $authorOnly] = self::FORMATS[$format];

        // Everyone still has to be allowed to read it at all; drafts stay the
        // author's business in every format.
        $this->authorize('view', $post);

        if ($authorOnly) {
            $this->authorize('update', $post);
        }

        $post->loadMissing(['persona', 'user', 'categories']);
        $manuscript = Manuscript::fromPost($post);

        $body = match ($format) {
            'markdown' => MarkdownExporter::render($manuscript),
            'bibtex' => BibtexExporter::render($manuscript),
            'latex' => LatexExporter::render($manuscript),
            'docx' => DocxExporter::render($manuscript),
        };

        $filename = Str::slug($post->title).'.'.$extension;

        return response()->streamDownload(
            fn () => print ($body),
            $filename,
            [
                'Content-Type' => $mime,
                // Anything served as a download from user content must not be
                // sniffed into something else by the browser.
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function optionsFor(Post $post, bool $isAuthor): array
    {
        return collect(self::FORMATS)
            ->reject(fn (array $spec) => $spec[2] && ! $isAuthor)
            ->map(fn (array $spec, string $format) => [
                'format' => $format,
                'label' => match ($format) {
                    'markdown' => 'Markdown (+ Obsidian)',
                    'bibtex' => 'BibTeX citation',
                    'latex' => 'IEEE LaTeX manuscript',
                    'docx' => 'Word manuscript',
                },
                'url' => route('posts.export', [$post, $format], absolute: false),
            ])
            ->values()
            ->all();
    }
}
