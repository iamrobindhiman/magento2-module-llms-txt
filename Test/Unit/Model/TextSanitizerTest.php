<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\TextSanitizer;

class TextSanitizerTest extends TestCase
{
    private TextSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new TextSanitizer();
    }

    // ── sanitize() ──

    public function testSanitizeDecodesTrademarkEntity(): void
    {
        $this->assertSame("Product\u{2122}", $this->sanitizer->sanitize('Product&trade;'));
    }

    public function testSanitizeDecodesRegisteredEntity(): void
    {
        $this->assertSame("Brand\u{00AE}", $this->sanitizer->sanitize('Brand&reg;'));
    }

    public function testSanitizeDecodesAmpersandEntity(): void
    {
        $this->assertSame('Salt & Pepper', $this->sanitizer->sanitize('Salt &amp; Pepper'));
    }

    public function testSanitizeDecodesQuotEntity(): void
    {
        $this->assertSame('She said "hello"', $this->sanitizer->sanitize('She said &quot;hello&quot;'));
    }

    public function testSanitizeCollapsesDoubleSpaces(): void
    {
        $this->assertSame('Frankie Sweatshirt', $this->sanitizer->sanitize('Frankie  Sweatshirt'));
    }

    public function testSanitizeCollapsesMultipleSpaces(): void
    {
        $this->assertSame('a b c', $this->sanitizer->sanitize('a    b     c'));
    }

    public function testSanitizeRemovesTrailingSpaces(): void
    {
        $this->assertSame('hello', $this->sanitizer->sanitize('hello   '));
    }

    public function testSanitizeRemovesLeadingSpaces(): void
    {
        $this->assertSame('hello', $this->sanitizer->sanitize('   hello'));
    }

    public function testSanitizeNormalizesTabCharacters(): void
    {
        $this->assertSame('col1 col2 col3', $this->sanitizer->sanitize("col1\tcol2\tcol3"));
    }

    public function testSanitizeNormalizesMixedTabsAndSpaces(): void
    {
        $this->assertSame('a b', $this->sanitizer->sanitize("a \t  \t b"));
    }

    public function testSanitizeReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    public function testSanitizePassesThroughCleanText(): void
    {
        $this->assertSame('Already clean text', $this->sanitizer->sanitize('Already clean text'));
    }

    public function testSanitizeHandlesMixedEntitiesAndWhitespace(): void
    {
        $this->assertSame(
            "Brand\u{00AE} Salt & Pepper\u{2122}",
            $this->sanitizer->sanitize('Brand&reg;  Salt &amp;  Pepper&trade;')
        );
    }

    // ── stripHtml() ──

    public function testStripHtmlRemovesTagsButPreservesText(): void
    {
        $this->assertSame(
            'Hello World',
            $this->sanitizer->stripHtml('<p><strong>Hello</strong> World</p>')
        );
    }

    public function testStripHtmlRemovesMagentoWidgetDirectives(): void
    {
        $result = $this->sanitizer->stripHtml(
            'Before {{widget type="Magento\Cms\Block\Widget\Block" template="widget/static_block/default.phtml"}} After'
        );
        $this->assertSame('Before After', $result);
    }

    public function testStripHtmlRemovesMagentoMediaDirective(): void
    {
        $result = $this->sanitizer->stripHtml('Image: {{media url="wysiwyg/image.jpg"}}');
        $this->assertSame('Image:', $result);
    }

    public function testStripHtmlRemovesScriptBlocksEntirely(): void
    {
        $result = $this->sanitizer->stripHtml(
            'Before<script type="text/javascript">alert("xss");</script>After'
        );
        $this->assertSame('BeforeAfter', $result);
    }

    public function testStripHtmlRemovesMultiLineScriptBlocks(): void
    {
        $html = "Before<script>\nvar x = 1;\nvar y = 2;\n</script>After";
        $this->assertSame('BeforeAfter', $this->sanitizer->stripHtml($html));
    }

    public function testStripHtmlRemovesStyleBlocksEntirely(): void
    {
        $result = $this->sanitizer->stripHtml(
            'Before<style>.red { color: red; }</style>After'
        );
        $this->assertSame('BeforeAfter', $result);
    }

    public function testStripHtmlRemovesMultiLineStyleBlocks(): void
    {
        $html = "Before<style>\nbody {\n  margin: 0;\n}\n</style>After";
        $this->assertSame('BeforeAfter', $this->sanitizer->stripHtml($html));
    }

    public function testStripHtmlDecodesHtmlEntities(): void
    {
        $this->assertSame(
            'Tom & Jerry',
            $this->sanitizer->stripHtml('<p>Tom &amp; Jerry</p>')
        );
    }

    public function testStripHtmlConvertsBulletCharactersToMarkdownListItems(): void
    {
        $result = $this->sanitizer->stripHtml('Features: • Fast • Reliable • Secure');
        $this->assertStringContainsString('- Fast', $result);
        $this->assertStringContainsString('- Reliable', $result);
        $this->assertStringContainsString('- Secure', $result);
    }

    public function testStripHtmlCollapsesExcessiveNewlines(): void
    {
        $result = $this->sanitizer->stripHtml("Line1\n\n\n\n\nLine2");
        $this->assertSame("Line1\n\nLine2", $result);
    }

    public function testStripHtmlReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->stripHtml(''));
    }

    public function testStripHtmlReturnsEmptyForOnlyHtmlTags(): void
    {
        $this->assertSame('', $this->sanitizer->stripHtml('<div><span></span></div>'));
    }

    public function testStripHtmlRemovesLeadingSpacesFromLines(): void
    {
        $result = $this->sanitizer->stripHtml("<p>   indented text</p>");
        $this->assertSame('indented text', $result);
    }

    // ── htmlToMarkdown() ──

    #[DataProvider('headingProvider')]
    public function testHtmlToMarkdownConvertsHeadingsToMarkdownBold(string $html, string $headingText): void
    {
        $result = $this->sanitizer->htmlToMarkdown($html);
        $this->assertStringContainsString("**{$headingText}**", $result);
    }

    public static function headingProvider(): array
    {
        return [
            'h1' => ['<h1>Title</h1>', 'Title'],
            'h2' => ['<h2>Subtitle</h2>', 'Subtitle'],
            'h3' => ['<h3>Section</h3>', 'Section'],
            'h4' => ['<h4>Subsection</h4>', 'Subsection'],
            'h5' => ['<h5>Minor</h5>', 'Minor'],
            'h6' => ['<h6>Smallest</h6>', 'Smallest'],
        ];
    }

    public function testHtmlToMarkdownConvertsBrToNewlines(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('Line1<br>Line2<br/>Line3<br />Line4');
        $this->assertStringContainsString("Line1\nLine2", $result);
        $this->assertStringContainsString("Line2\nLine3", $result);
        $this->assertStringContainsString("Line3\nLine4", $result);
    }

    public function testHtmlToMarkdownConvertsClosePOpenPToDoubleNewlines(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<p>First paragraph</p><p>Second paragraph</p>');
        $this->assertStringContainsString("First paragraph\n\nSecond paragraph", $result);
    }

    public function testHtmlToMarkdownConvertsListItemsToMarkdownDash(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<ul><li>Alpha</li><li>Beta</li></ul>');
        $this->assertStringContainsString('- Alpha', $result);
        $this->assertStringContainsString('- Beta', $result);
    }

    public function testHtmlToMarkdownConvertsBulletCharactersToMarkdownListItems(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('Features: • Fast • Strong');
        $this->assertStringContainsString('- Fast', $result);
        $this->assertStringContainsString('- Strong', $result);
    }

    public function testHtmlToMarkdownConvertsTableToMarkdownFormat(): void
    {
        $html = '<table><tr><th>Name</th><th>Price</th></tr><tr><td>Widget</td><td>$10</td></tr></table>';
        $result = $this->sanitizer->htmlToMarkdown($html);
        $this->assertStringContainsString('| Name | Price |', $result);
        $this->assertStringContainsString('| Widget | $10 |', $result);
        $this->assertMatchesRegularExpression('/\| -{3,} \| -{3,} \|/', $result);
    }

    public function testHtmlToMarkdownConvertsTableWithThead(): void
    {
        $html = '<table><thead><tr><th>Region</th><th>Rate</th></tr></thead>'
            . '<tbody><tr><td>US</td><td>Free</td></tr><tr><td>EU</td><td>$5</td></tr></tbody></table>';
        $result = $this->sanitizer->htmlToMarkdown($html);
        $this->assertStringContainsString('| Region | Rate |', $result);
        $this->assertStringContainsString('| US | Free |', $result);
        $this->assertStringContainsString('| EU | $5 |', $result);
    }

    public function testHtmlToMarkdownRemovesScriptBlocks(): void
    {
        $result = $this->sanitizer->htmlToMarkdown(
            '<p>Safe</p><script>alert("xss")</script><p>Content</p>'
        );
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Safe', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function testHtmlToMarkdownRemovesStyleBlocks(): void
    {
        $result = $this->sanitizer->htmlToMarkdown(
            '<p>Visible</p><style>body{display:none}</style>'
        );
        $this->assertStringNotContainsString('display', $result);
        $this->assertStringContainsString('Visible', $result);
    }

    public function testHtmlToMarkdownRemovesWidgetDirectives(): void
    {
        $result = $this->sanitizer->htmlToMarkdown(
            '<p>Text</p>{{widget type="Magento\Catalog\Block\Product\Widget\NewWidget"}}<p>More</p>'
        );
        $this->assertStringNotContainsString('widget', $result);
        $this->assertStringContainsString('Text', $result);
        $this->assertStringContainsString('More', $result);
    }

    public function testHtmlToMarkdownReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->sanitizer->htmlToMarkdown(''));
    }

    public function testHtmlToMarkdownHandlesComplexMixedHtml(): void
    {
        $html = '<h2>Shipping Policy</h2>'
            . '<p>We ship worldwide.</p><p>Rates below:</p>'
            . '<table><tr><th>Region</th><th>Cost</th></tr><tr><td>US</td><td>Free</td></tr></table>'
            . '<ul><li>Fast delivery</li><li>Tracking included</li></ul>'
            . '{{widget type="Magento\Cms\Block\Widget\Block"}}'
            . '<script>trackPage();</script>'
            . '<style>.hidden{display:none}</style>';

        $result = $this->sanitizer->htmlToMarkdown($html);

        $this->assertStringContainsString('**Shipping Policy**', $result);
        $this->assertStringContainsString('We ship worldwide.', $result);
        $this->assertStringContainsString('| Region | Cost |', $result);
        $this->assertStringContainsString('| US | Free |', $result);
        $this->assertStringContainsString('- Fast delivery', $result);
        $this->assertStringContainsString('- Tracking included', $result);
        $this->assertStringNotContainsString('widget', $result);
        $this->assertStringNotContainsString('trackPage', $result);
        $this->assertStringNotContainsString('display:none', $result);
    }

    public function testHtmlToMarkdownCollapsesExcessiveNewlines(): void
    {
        $result = $this->sanitizer->htmlToMarkdown("<p>A</p>\n\n\n\n<p>B</p>");
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $result);
    }

    public function testHtmlToMarkdownConvertsHeadingsWithAttributes(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<h2 class="title" id="main">Styled Heading</h2>');
        $this->assertStringContainsString('**Styled Heading**', $result);
    }

    public function testHtmlToMarkdownConvertsListItemsWithAttributes(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<li class="item">Attributed Item</li>');
        $this->assertStringContainsString('- Attributed Item', $result);
    }

    public function testHtmlToMarkdownDecodesEntitiesAfterConversion(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<p>Price: $10 &amp; up</p>');
        $this->assertStringContainsString('Price: $10 & up', $result);
    }

    public function testHtmlToMarkdownHandlesTableWithNoRows(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<table></table>');
        $this->assertStringNotContainsString('|', $result);
    }

    public function testHtmlToMarkdownStripsNestedTagsInsideHeadings(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<h3><span>Nested</span></h3>');
        $this->assertStringContainsString('**Nested**', $result);
    }

    public function testHtmlToMarkdownParagraphSpacingWithWhitespace(): void
    {
        $result = $this->sanitizer->htmlToMarkdown('<p>First</p>  <p>Second</p>');
        $this->assertStringContainsString("First\n\nSecond", $result);
    }
}
