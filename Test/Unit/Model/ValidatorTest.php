<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Validator;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testValidContentReturnsNoErrors(): void
    {
        $content = <<<'MD'
# My Store

> My Store product catalog for AI assistants.

## Products

- [Product 1](http://example.com/product-1.html): Great product
- [Product 2](http://example.com/product-2.html): Another product
MD;

        $errors = $this->validator->validate($content);
        $this->assertEmpty($errors, 'Valid content should return no errors');
    }

    public function testEmptyContentReturnsError(): void
    {
        $errors = $this->validator->validate('');
        $this->assertNotEmpty($errors);
        $this->assertContains('File is empty', $errors);
    }

    public function testWhitespaceOnlyContentReturnsError(): void
    {
        $errors = $this->validator->validate("   \n\n  \t  ");
        $this->assertNotEmpty($errors);
        $this->assertContains('File is empty', $errors);
    }

    public function testMissingTopLevelHeadingReturnsError(): void
    {
        $content = "Some text without heading\n\n## Section\n- content";
        $errors = $this->validator->validate($content);
        $this->assertContains(
            'File must start with a top-level heading (# Title)',
            $errors
        );
    }

    public function testMissingSectionReturnsError(): void
    {
        $content = "# Title\n\n> Description\n\nSome content without sections.";
        $errors = $this->validator->validate($content);
        $this->assertContains(
            'File should contain at least one section (## Section Name)',
            $errors
        );
    }

    public function testInvalidUrlInLinkReturnsError(): void
    {
        $content = <<<'MD'
# Title

## Section

- [Bad Link](not-a-url): Invalid
MD;

        $errors = $this->validator->validate($content);
        $this->assertNotEmpty($errors);
        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'Invalid URL for link "Bad Link"')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should report invalid URL');
    }

    public function testValidUrlsReturnNoLinkErrors(): void
    {
        $content = <<<'MD'
# Title

## Section

- [Valid Link](https://example.com/page): Description
- [Another](http://localhost:8888/product.html): Local link
MD;

        $errors = $this->validator->validate($content);
        $this->assertEmpty($errors);
    }

    public function testOversizedFileReturnsWarning(): void
    {
        // Generate content larger than 10MB (each line ~50 bytes × 220K = ~11MB)
        $content = "# Title\n\n## Section\n\n";
        $content .= str_repeat("- [Product](http://example.com/product-page): A detailed product description line\n", 220000);

        $errors = $this->validator->validate($content);
        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'exceeds recommended maximum')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should warn about oversized file');
    }

    public function testContentWithOnlyHeadingAndSectionPasses(): void
    {
        $content = "# Store\n\n## Info\n\nSome info here.";
        $errors = $this->validator->validate($content);
        $this->assertEmpty($errors);
    }
}
