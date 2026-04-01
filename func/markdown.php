<?php
/**
 * Markdown parsing helper for ClassLink
 * Uses league/commonmark with GitHub Flavored Markdown (GFM) extension.
 * GFM adds support for tables, strikethrough, task lists, and autolinks.
 *
 * Merged/colspan table cells are supported via the || column-span syntax:
 *
 *   | Column 1 | Column 2 | Column 3 |
 *   | -------- | -------- | -------- |
 *   | Merged A+B      || Column 3  |
 *   | Full row span         |||
 *
 * Each trailing || absorbs one extra column into the preceding cell's colspan.
 */

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Parses a Markdown string and returns safe HTML with Bootstrap classes applied.
 * Supports merged table cells via the || column-span syntax.
 *
 * @param string $markdown Raw Markdown content
 * @return string Rendered HTML
 */
function parse_markdown(string $markdown): string {
    require_once __DIR__ . '/../vendor/autoload.php';

    $environment = new Environment([
        'html_input'         => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level'  => 100,
    ]);

    $environment->addExtension(new CommonMarkCoreExtension());
    $environment->addExtension(new GithubFlavoredMarkdownExtension());

    $converter = new MarkdownConverter($environment);
    $html = (string) $converter->convert($markdown);

    // Apply colspan merging for the || table syntax.
    // GFM renders each || as an empty <td></td>; we post-process those into colspan.
    $html = apply_cell_merging($html);

    // Apply Bootstrap CSS classes to rendered HTML elements
    $replacements = [
        '<table>'      => '<table class="table table-bordered table-striped table-hover">',
        '<thead>'      => '<thead class="table-dark">',
        '<img '        => '<img class="img-fluid rounded" ',
        '<pre>'        => '<pre class="bg-body-secondary p-3 rounded">',
        '<blockquote>' => '<blockquote class="blockquote border-start border-4 ps-3 text-muted">',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $html);
}

/**
 * Post-processes rendered HTML tables to merge empty cells (produced by the ||
 * column-span syntax) into their predecessor's colspan attribute.
 *
 * @param string $html Rendered HTML output from CommonMark
 * @return string HTML with colspan attributes applied
 */
function apply_cell_merging(string $html): string {
    if (!extension_loaded('dom') || !str_contains($html, '<table')) {
        return $html;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>'
    );
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('tr') as $row) {
        $cells = [];
        foreach ($row->childNodes as $node) {
            if ($node instanceof DOMElement &&
                in_array($node->nodeName, ['td', 'th'], true)) {
                $cells[] = $node;
            }
        }

        $prev = null;
        $to_remove = [];
        foreach ($cells as $cell) {
            if (trim($cell->textContent) === '' && $prev !== null) {
                // Empty cell: absorb into the previous cell's colspan
                $current_span = (int) $prev->getAttribute('colspan') ?: 1;
                $prev->setAttribute('colspan', (string) ($current_span + 1));
                $to_remove[] = $cell;
            } else {
                $prev = $cell;
            }
        }

        foreach ($to_remove as $cell) {
            $row->removeChild($cell);
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return $html;
    }

    $result = '';
    foreach ($body->childNodes as $node) {
        $result .= $dom->saveHTML($node);
    }

    return $result;
}
?>

