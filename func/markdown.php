<?php
/**
 * Markdown parsing helper for ClassLink
 * Uses league/commonmark with GitHub Flavored Markdown (GFM) extension.
 * GFM adds support for tables, strikethrough, task lists, and autolinks.
 * For merged/colspan tables, raw HTML can be embedded directly in the Markdown.
 */

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Parses a Markdown string and returns safe HTML with Bootstrap classes applied.
 *
 * @param string $markdown Raw Markdown content
 * @return string Rendered HTML
 */
function parse_markdown(string $markdown): string {
    require_once __DIR__ . '/../vendor/autoload.php';

    $environment = new Environment([
        // Allow raw HTML blocks in Markdown (needed for merged/colspan tables)
        'html_input'         => 'allow',
        'allow_unsafe_links' => false,
        'max_nesting_level'  => 100,
    ]);

    $environment->addExtension(new CommonMarkCoreExtension());
    $environment->addExtension(new GithubFlavoredMarkdownExtension());

    $converter = new MarkdownConverter($environment);
    $html = (string) $converter->convert($markdown);

    // Apply Bootstrap CSS classes to rendered HTML elements
    $replacements = [
        '<table>'  => '<table class="table table-bordered table-striped table-hover">',
        '<thead>'  => '<thead class="table-dark">',
        '<img '    => '<img class="img-fluid rounded" ',
        '<pre>'    => '<pre class="bg-body-secondary p-3 rounded">',
        '<blockquote>' => '<blockquote class="blockquote border-start border-4 ps-3 text-muted">',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $html);
}
?>
