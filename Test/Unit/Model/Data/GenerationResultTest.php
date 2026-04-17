<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model\Data;

use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Data\GenerationResult;

class GenerationResultTest extends TestCase
{
    private GenerationResult $result;

    protected function setUp(): void
    {
        $this->result = new GenerationResult();
    }

    public function testSetAndGetSuccess(): void
    {
        $this->result->setSuccess(true);
        $this->assertTrue($this->result->isSuccess());

        $this->result->setSuccess(false);
        $this->assertFalse($this->result->isSuccess());
    }

    public function testSetAndGetFileType(): void
    {
        $this->result->setFileType('both');
        $this->assertSame('both', $this->result->getFileType());
    }

    public function testSetAndGetSectionsCount(): void
    {
        $this->result->setSectionsCount(4);
        $this->assertSame(4, $this->result->getSectionsCount());
    }

    public function testSetAndGetProductsCount(): void
    {
        $this->result->setProductsCount(181);
        $this->assertSame(181, $this->result->getProductsCount());
    }

    public function testSetAndGetFileSizeBytes(): void
    {
        $this->result->setFileSizeBytes(71160);
        $this->assertSame(71160, $this->result->getFileSizeBytes());
    }

    public function testSetAndGetDurationSeconds(): void
    {
        $this->result->setDurationSeconds(0.22);
        $this->assertSame(0.22, $this->result->getDurationSeconds());
    }

    public function testSetAndGetValidationErrors(): void
    {
        $errors = ['Error 1', 'Error 2'];
        $this->result->setValidationErrors($errors);
        $this->assertSame($errors, $this->result->getValidationErrors());
    }

    public function testGetValidationErrorsReturnsEmptyArrayByDefault(): void
    {
        $this->assertSame([], $this->result->getValidationErrors());
    }

    public function testSetAndGetStatus(): void
    {
        $this->result->setStatus('success');
        $this->assertSame('success', $this->result->getStatus());

        $this->result->setStatus('error');
        $this->assertSame('error', $this->result->getStatus());
    }

    public function testFluentInterface(): void
    {
        $result = $this->result
            ->setSuccess(true)
            ->setFileType('llms_txt')
            ->setSectionsCount(3)
            ->setProductsCount(100)
            ->setFileSizeBytes(5000)
            ->setDurationSeconds(1.5)
            ->setValidationErrors([])
            ->setStatus('success');

        $this->assertInstanceOf(GenerationResult::class, $result);
    }
}
