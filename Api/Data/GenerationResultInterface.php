<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Api\Data;

/**
 * Generation Result Data Transfer Object
 *
 * Returned after generating llms.txt files to report the outcome.
 *
 * @api
 */
interface GenerationResultInterface
{
    public const SUCCESS = 'success';
    public const FILE_TYPE = 'file_type';
    public const SECTIONS_COUNT = 'sections_count';
    public const PRODUCTS_COUNT = 'products_count';
    public const FILE_SIZE_BYTES = 'file_size_bytes';
    public const DURATION_SECONDS = 'duration_seconds';
    public const VALIDATION_ERRORS = 'validation_errors';
    public const STATUS = 'status';

    /**
     * Whether generation was successful
     *
     * @return bool
     */
    public function isSuccess(): bool;

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self;

    /**
     * Get file type generated (llms_txt, llms_full_txt, both)
     *
     * @return string
     */
    public function getFileType(): string;

    /**
     * Set file type
     *
     * @param string $fileType
     * @return $this
     */
    public function setFileType(string $fileType): self;

    /**
     * Get number of sections generated
     *
     * @return int
     */
    public function getSectionsCount(): int;

    /**
     * Set sections count
     *
     * @param int $count
     * @return $this
     */
    public function setSectionsCount(int $count): self;

    /**
     * Get number of products included
     *
     * @return int
     */
    public function getProductsCount(): int;

    /**
     * Set products count
     *
     * @param int $count
     * @return $this
     */
    public function setProductsCount(int $count): self;

    /**
     * Get generated file size in bytes
     *
     * @return int
     */
    public function getFileSizeBytes(): int;

    /**
     * Set file size
     *
     * @param int $bytes
     * @return $this
     */
    public function setFileSizeBytes(int $bytes): self;

    /**
     * Get generation duration in seconds
     *
     * @return float
     */
    public function getDurationSeconds(): float;

    /**
     * Set duration
     *
     * @param float $seconds
     * @return $this
     */
    public function setDurationSeconds(float $seconds): self;

    /**
     * Get validation errors (empty array = valid)
     *
     * @return string[]
     */
    public function getValidationErrors(): array;

    /**
     * Set validation errors
     *
     * @param string[] $errors
     * @return $this
     */
    public function setValidationErrors(array $errors): self;

    /**
     * Get status string (success, error, partial)
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;
}
