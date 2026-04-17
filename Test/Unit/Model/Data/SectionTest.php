<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model\Data;

use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Data\Section;

class SectionTest extends TestCase
{
    private Section $section;

    protected function setUp(): void
    {
        $this->section = new Section();
    }

    public function testSetAndGetName(): void
    {
        $this->section->setName('Products');
        $this->assertSame('Products', $this->section->getName());
    }

    public function testSetAndGetPriority(): void
    {
        $this->section->setPriority(40);
        $this->assertSame(40, $this->section->getPriority());
    }

    public function testSetAndGetSummary(): void
    {
        $this->section->setSummary('Product catalog (100 products)');
        $this->assertSame('Product catalog (100 products)', $this->section->getSummary());
    }

    public function testSetAndGetLinks(): void
    {
        $links = ['Product A' => 'http://example.com/a', 'Product B' => 'http://example.com/b'];
        $this->section->setLinks($links);
        $this->assertSame($links, $this->section->getLinks());
    }

    public function testGetLinksReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->section->getLinks());
    }

    public function testSetAndGetFullContent(): void
    {
        $content = "### Product A\n\nFull description here.";
        $this->section->setFullContent($content);
        $this->assertSame($content, $this->section->getFullContent());
    }

    public function testSetAndGetItemCount(): void
    {
        $this->section->setItemCount(42);
        $this->assertSame(42, $this->section->getItemCount());
    }

    public function testDefaultValuesAreEmptyOrZero(): void
    {
        $this->assertSame('', $this->section->getName());
        $this->assertSame(0, $this->section->getPriority());
        $this->assertSame('', $this->section->getSummary());
        $this->assertSame([], $this->section->getLinks());
        $this->assertSame('', $this->section->getFullContent());
        $this->assertSame(0, $this->section->getItemCount());
    }

    public function testFluentInterface(): void
    {
        $result = $this->section
            ->setName('Test')
            ->setPriority(10)
            ->setSummary('Summary')
            ->setLinks(['link' => 'http://example.com'])
            ->setFullContent('Content')
            ->setItemCount(5);

        $this->assertInstanceOf(Section::class, $result);
        $this->assertSame('Test', $result->getName());
    }
}
