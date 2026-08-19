<?php

namespace App\Support\Academic;

use RuntimeException;
use ZipArchive;

/**
 * A real .docx, written without a document library.
 *
 * A .docx is a zip of XML parts. The minimum Word will open is four: the
 * content-types map, the package relationship pointing at the document, the
 * document's own relationships, and the document body. That is small enough to
 * write directly and avoids taking a dependency on a library an order of
 * magnitude larger than the feature.
 *
 * Everything interpolated into the XML is escaped — a stray `&` in a title is
 * enough to make Word refuse the file as corrupt, with no useful message.
 */
class DocxExporter
{
    public static function render(Manuscript $manuscript): string
    {
        $file = tempnam(sys_get_temp_dir(), 'inkfathom-docx-');

        if ($file === false) {
            throw new RuntimeException('Could not allocate a temporary file for the export.');
        }

        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Could not open the export archive for writing.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::packageRels());
        $zip->addFromString('word/_rels/document.xml.rels', self::documentRels());
        $zip->addFromString('word/styles.xml', self::styles());
        $zip->addFromString('word/document.xml', self::document($manuscript));
        $zip->close();

        $contents = file_get_contents($file);
        unlink($file);

        if ($contents === false) {
            throw new RuntimeException('Could not read the generated document.');
        }

        return $contents;
    }

    private static function document(Manuscript $manuscript): string
    {
        $body = self::paragraph($manuscript->title, 'Title');
        $body .= self::paragraph($manuscript->authorName, 'Author');

        if ($manuscript->orcid) {
            $body .= self::paragraph('ORCID: '.$manuscript->orcid, 'Author');
        }

        if ($manuscript->publishedAt) {
            $body .= self::paragraph($manuscript->publishedAt, 'Author');
        }

        if ($manuscript->abstract !== '') {
            $body .= self::paragraph('Abstract', 'Heading2');
            $body .= self::paragraph($manuscript->abstract);
        }

        if ($manuscript->keywords !== []) {
            $body .= self::paragraph('Keywords: '.implode(', ', $manuscript->keywords));
        }

        foreach (BlockParser::parse($manuscript->bodyHtml) as $block) {
            $body .= match ($block['type']) {
                'heading' => self::paragraph($block['text'], 'Heading'.min(3, $block['level'])),
                'quote' => self::paragraph($block['text'], 'Quote'),
                'code' => self::paragraph($block['text'], 'Code'),
                'figure' => self::paragraph($block['text'], 'Quote'),
                'bullets', 'numbers' => implode('', array_map(
                    fn (string $item, int $index) => self::paragraph(
                        ($block['type'] === 'numbers' ? ($index + 1).'. ' : '• ').$item,
                    ),
                    $block['items'] ?? [],
                    array_keys($block['items'] ?? []),
                )),
                default => self::paragraph($block['text']),
            };
        }

        $body .= self::paragraph('Source: '.$manuscript->url);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private static function paragraph(string $text, ?string $style = null): string
    {
        $properties = $style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '';

        // xml:space="preserve" keeps leading and trailing spaces, which Word
        // otherwise collapses — visible immediately in indented code.
        return '<w:p>'.$properties.'<w:r><w:t xml:space="preserve">'.self::escape($text).'</w:t></w:r></w:p>';
    }

    private static function escape(string $value): string
    {
        // Control characters are not legal in XML 1.0 at all, and Word rejects
        // the whole document rather than skipping them.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private static function packageRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private static function documentRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * Named styles, so the export lands as a document an editor can restyle
     * rather than a wall of hard formatting.
     */
    private static function styles(): string
    {
        $style = static fn (string $id, string $name, string $properties) => '<w:style w:type="paragraph" w:styleId="'.$id.'">'
            .'<w:name w:val="'.$name.'"/><w:pPr>'.$properties.'</w:pPr></w:style>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .$style('Title', 'Title', '<w:spacing w:after="240"/>')
            .$style('Author', 'Author', '<w:spacing w:after="120"/>')
            .$style('Heading1', 'heading 1', '<w:outlineLvl w:val="0"/><w:spacing w:before="360" w:after="120"/>')
            .$style('Heading2', 'heading 2', '<w:outlineLvl w:val="1"/><w:spacing w:before="320" w:after="120"/>')
            .$style('Heading3', 'heading 3', '<w:outlineLvl w:val="2"/><w:spacing w:before="280" w:after="120"/>')
            .$style('Quote', 'Quote', '<w:ind w:left="720"/><w:spacing w:after="160"/>')
            .$style('Code', 'HTMLCode', '<w:ind w:left="360"/><w:spacing w:after="160"/>')
            .'</w:styles>';
    }
}
