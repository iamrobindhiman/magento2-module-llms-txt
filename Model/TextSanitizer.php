<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

/**
 * Shared text sanitizer for all llms.txt output
 *
 * Single point of sanitization — every text value from the database
 * passes through this class before being written to llms.txt output.
 *
 * Fixes: BUG-01 (HTML entities), BUG-02 (dirty whitespace), NEW-04 (CMS artifacts)
 */
class TextSanitizer
{
    /**
     * Sanitize plain text (product names, category names, store names)
     *
     * Decodes HTML entities and normalizes whitespace.
     * Apply to ALL text before writing to file.
     *
     * @param string $text
     * @return string
     */
    public function sanitize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Decode HTML entities: &trade; → ™, &reg; → ®, &amp; → &, &quot; → "
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse multiple spaces/tabs into single space
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        // Trim leading/trailing whitespace
        $text = trim($text);

        return $text;
    }

    /**
     * Strip HTML tags and sanitize for plain text output
     *
     * Use for content fields that may contain HTML (descriptions, CMS content).
     *
     * @param string $html
     * @return string
     */
    public function stripHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove Magento widget/directive placeholders
        $text = (string) preg_replace('/\{\{[^}]+\}\}/', '', $html);

        // Remove script and style blocks entirely (before strip_tags, which leaves their content)
        $text = (string) preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
        $text = (string) preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);

        // Strip all remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Convert bullet characters to markdown list items
        $text = (string) preg_replace('/•\s*/', "\n- ", $text);

        // Collapse multiple spaces/tabs into single space (per line)
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        // Remove leading spaces from each line
        $text = (string) preg_replace('/^ +/m', '', $text);

        // Collapse 3+ consecutive newlines into 2
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Strip HTML and convert to markdown-friendly text for CMS pages
     *
     * More aggressive cleanup than stripHtml() — handles table remnants,
     * heading conversion, paragraph breaks.
     *
     * @param string $html
     * @return string
     */
    public function htmlToMarkdown(string $html): string
    {
        if ($html === '') {
            return '';
        }

        // Remove Magento widget/directive placeholders
        $text = (string) preg_replace('/\{\{[^}]+\}\}/', '', $html);

        // Remove script and style blocks entirely
        $text = (string) preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
        $text = (string) preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);

        // Convert HTML tables to markdown tables BEFORE stripping tags
        $text = $this->convertTablesToMarkdown($text);

        // Convert headings to markdown bold
        $text = (string) preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', "\n\n**$1**\n\n", $text);

        // Convert <br> to newline
        $text = (string) preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Convert </p><p> to double newline
        $text = (string) preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $text);

        // Convert unordered list items to markdown
        $text = (string) preg_replace('/<li[^>]*>/i', "\n- ", $text);

        // Convert bullet characters (•) to proper markdown list items
        $text = (string) preg_replace('/•\s*/', "\n- ", $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse multiple spaces/tabs into single space (per line)
        $text = (string) preg_replace('/[ \t]+/', ' ', $text);

        // Remove leading spaces from each line
        $text = (string) preg_replace('/^ +/m', '', $text);

        // Collapse 3+ consecutive newlines into 2
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        $text = trim($text);

        return $text;
    }

    /**
     * Convert HTML tables to markdown tables
     *
     * Preserves tabular data structure (e.g., shipping rate tables)
     * that would otherwise be lost when stripping HTML tags.
     *
     * @param string $html
     * @return string HTML with tables replaced by markdown
     */
    private function convertTablesToMarkdown(string $html): string
    {
        // Match complete <table>...</table> blocks
        if (!preg_match_all('/<table[^>]*>(.*?)<\/table>/is', $html, $tables)) {
            return $html;
        }

        foreach ($tables[0] as $index => $fullTable) {
            $tableContent = $tables[1][$index];
            $markdownTable = $this->parseTableToMarkdown($tableContent);
            $html = str_replace($fullTable, "\n\n" . $markdownTable . "\n\n", $html);
        }

        return $html;
    }

    /**
     * Parse inner table HTML into markdown table format
     *
     * @param string $tableHtml
     * @return string
     */
    private function parseTableToMarkdown(string $tableHtml): string
    {
        $rows = [];

        // Extract all rows (both thead and tbody)
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $tableHtml, $trMatches)) {
            foreach ($trMatches[1] as $trContent) {
                $cells = [];
                // Match both <th> and <td>
                if (preg_match_all('/<(?:th|td)[^>]*>(.*?)<\/(?:th|td)>/is', $trContent, $cellMatches)) {
                    foreach ($cellMatches[1] as $cellContent) {
                        $cell = strip_tags($cellContent);
                        $cell = html_entity_decode($cell, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $cell = trim((string) preg_replace('/\s+/', ' ', $cell));
                        $cells[] = $cell;
                    }
                }
                if (!empty($cells)) {
                    $rows[] = $cells;
                }
            }
        }

        if (empty($rows)) {
            return '';
        }

        // Build markdown table
        $lines = [];
        foreach ($rows as $rowIndex => $cells) {
            $lines[] = '| ' . implode(' | ', $cells) . ' |';
            // Add separator after first row (header)
            if ($rowIndex === 0) {
                $separators = array_map(static function ($cell) {
                    return str_repeat('-', max(3, mb_strlen($cell)));
                }, $cells);
                $lines[] = '| ' . implode(' | ', $separators) . ' |';
            }
        }

        return implode("\n", $lines);
    }
}
