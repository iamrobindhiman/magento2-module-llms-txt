<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

/**
 * Validates llms.txt content against the specification
 *
 * Checks:
 * - File starts with a markdown heading (# Title)
 * - Contains at least one section
 * - Links use valid markdown format [title](url)
 * - URLs are well-formed
 * - File is not empty
 */
class Validator
{
    /**
     * Validate llms.txt content against spec
     *
     * @param string $content The generated llms.txt content
     * @return string[] Array of validation error messages (empty = valid)
     */
    public function validate(string $content): array
    {
        $errors = [];

        if (trim($content) === '') {
            $errors[] = 'File is empty';
            return $errors;
        }

        $lines = explode("\n", $content);
        $firstNonEmptyLine = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $firstNonEmptyLine = trim($line);
                break;
            }
        }

        // Must start with a top-level heading
        if (!str_starts_with($firstNonEmptyLine, '# ')) {
            $errors[] = 'File must start with a top-level heading (# Title)';
        }

        // Should contain at least one section (## heading)
        if (!preg_match('/^## /m', $content)) {
            $errors[] = 'File should contain at least one section (## Section Name)';
        }

        // Validate all markdown links have well-formed URLs
        if (preg_match_all('/\[([^\]]*)\]\(([^)]*)\)/', $content, $matches)) {
            foreach ($matches[2] as $index => $url) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $linkTitle = $matches[1][$index];
                    $errors[] = sprintf('Invalid URL for link "%s": %s', $linkTitle, $url);
                }
            }
        }

        // Check file size is reasonable (warn if over 10MB)
        $sizeBytes = strlen($content);
        if ($sizeBytes > 10 * 1024 * 1024) {
            $sizeMb = round($sizeBytes / (1024 * 1024), 1);
            $errors[] = sprintf('File size (%s MB) exceeds recommended maximum of 10 MB', $sizeMb);
        }

        return $errors;
    }
}
