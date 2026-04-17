<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Api\Data;

/**
 * Section Data Transfer Object
 *
 * Represents a single section in the llms.txt output.
 *
 * @api
 */
interface SectionInterface
{
    public const NAME = 'name';
    public const PRIORITY = 'priority';
    public const SUMMARY = 'summary';
    public const LINKS = 'links';
    public const FULL_CONTENT = 'full_content';
    public const ITEM_COUNT = 'item_count';
    public const WARNINGS = 'warnings';

    /**
     * Get section name (used as markdown heading)
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set section name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get section priority
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set section priority
     *
     * @param int $priority
     * @return $this
     */
    public function setPriority(int $priority): self;

    /**
     * Get section summary text (for llms.txt)
     *
     * @return string
     */
    public function getSummary(): string;

    /**
     * Set section summary
     *
     * @param string $summary
     * @return $this
     */
    public function setSummary(string $summary): self;

    /**
     * Get links array for llms.txt (title => url pairs)
     *
     * @return array<string, string>
     */
    public function getLinks(): array;

    /**
     * Set links array
     *
     * @param array<string, string> $links
     * @return $this
     */
    public function setLinks(array $links): self;

    /**
     * Get full content for llms-full.txt
     *
     * @return string
     */
    public function getFullContent(): string;

    /**
     * Set full content
     *
     * @param string $content
     * @return $this
     */
    public function setFullContent(string $content): self;

    /**
     * Get number of items in this section
     *
     * @return int
     */
    public function getItemCount(): int;

    /**
     * Set item count
     *
     * @param int $count
     * @return $this
     */
    public function setItemCount(int $count): self;

    /**
     * Get non-blocking warnings produced while building this section (e.g. over-limit notice)
     *
     * @return string[]
     */
    public function getWarnings(): array;

    /**
     * Set warnings
     *
     * @param string[] $warnings
     * @return $this
     */
    public function setWarnings(array $warnings): self;
}
