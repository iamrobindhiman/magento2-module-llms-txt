<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Data;

use Magento\Framework\DataObject;
use RKD\LlmsTxt\Api\Data\GenerationResultInterface;

class GenerationResult extends DataObject implements GenerationResultInterface
{
    /**
     * @inheritdoc
     */
    public function isSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * @inheritdoc
     */
    public function setSuccess(bool $success): GenerationResultInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * @inheritdoc
     */
    public function getFileType(): string
    {
        return (string) $this->getData(self::FILE_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setFileType(string $fileType): GenerationResultInterface
    {
        return $this->setData(self::FILE_TYPE, $fileType);
    }

    /**
     * @inheritdoc
     */
    public function getSectionsCount(): int
    {
        return (int) $this->getData(self::SECTIONS_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setSectionsCount(int $count): GenerationResultInterface
    {
        return $this->setData(self::SECTIONS_COUNT, $count);
    }

    /**
     * @inheritdoc
     */
    public function getProductsCount(): int
    {
        return (int) $this->getData(self::PRODUCTS_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setProductsCount(int $count): GenerationResultInterface
    {
        return $this->setData(self::PRODUCTS_COUNT, $count);
    }

    /**
     * @inheritdoc
     */
    public function getFileSizeBytes(): int
    {
        return (int) $this->getData(self::FILE_SIZE_BYTES);
    }

    /**
     * @inheritdoc
     */
    public function setFileSizeBytes(int $bytes): GenerationResultInterface
    {
        return $this->setData(self::FILE_SIZE_BYTES, $bytes);
    }

    /**
     * @inheritdoc
     */
    public function getDurationSeconds(): float
    {
        return (float) $this->getData(self::DURATION_SECONDS);
    }

    /**
     * @inheritdoc
     */
    public function setDurationSeconds(float $seconds): GenerationResultInterface
    {
        return $this->setData(self::DURATION_SECONDS, $seconds);
    }

    /**
     * @inheritdoc
     */
    public function getValidationErrors(): array
    {
        return $this->getData(self::VALIDATION_ERRORS) ?? [];
    }

    /**
     * @inheritdoc
     */
    public function setValidationErrors(array $errors): GenerationResultInterface
    {
        return $this->setData(self::VALIDATION_ERRORS, $errors);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    /**
     * @inheritdoc
     */
    public function setStatus(string $status): GenerationResultInterface
    {
        return $this->setData(self::STATUS, $status);
    }
}
