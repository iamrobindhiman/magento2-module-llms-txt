<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Data;

use Magento\Framework\DataObject;
use RKD\LlmsTxt\Api\Data\SectionInterface;

class Section extends DataObject implements SectionInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): SectionInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return (int) $this->getData(self::PRIORITY);
    }

    /**
     * @inheritdoc
     */
    public function setPriority(int $priority): SectionInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritdoc
     */
    public function getSummary(): string
    {
        return (string) $this->getData(self::SUMMARY);
    }

    /**
     * @inheritdoc
     */
    public function setSummary(string $summary): SectionInterface
    {
        return $this->setData(self::SUMMARY, $summary);
    }

    /**
     * @inheritdoc
     */
    public function getLinks(): array
    {
        return $this->getData(self::LINKS) ?? [];
    }

    /**
     * @inheritdoc
     */
    public function setLinks(array $links): SectionInterface
    {
        return $this->setData(self::LINKS, $links);
    }

    /**
     * @inheritdoc
     */
    public function getFullContent(): string
    {
        return (string) $this->getData(self::FULL_CONTENT);
    }

    /**
     * @inheritdoc
     */
    public function setFullContent(string $content): SectionInterface
    {
        return $this->setData(self::FULL_CONTENT, $content);
    }

    /**
     * @inheritdoc
     */
    public function getItemCount(): int
    {
        return (int) $this->getData(self::ITEM_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setItemCount(int $count): SectionInterface
    {
        return $this->setData(self::ITEM_COUNT, $count);
    }

    /**
     * @inheritdoc
     */
    public function getWarnings(): array
    {
        return $this->getData(self::WARNINGS) ?? [];
    }

    /**
     * @inheritdoc
     */
    public function setWarnings(array $warnings): SectionInterface
    {
        return $this->setData(self::WARNINGS, $warnings);
    }
}
