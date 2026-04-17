<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Writes llms.txt and llms-full.txt files to var/rkd_llmstxt/{store_code}/
 *
 * Multi-store: each store view gets its own directory.
 * Files are in var/ so all access goes through our Router → Controller.
 * Atomic write pattern: temp file → rename.
 */
class FileWriter
{
    private const LLMS_TXT_FILENAME = 'llms.txt';
    private const LLMS_FULL_TXT_FILENAME = 'llms-full.txt';
    private const STORAGE_DIR = 'rkd_llmstxt';
    private const TEMP_PREFIX = '.llmstxt_tmp_';

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Write llms.txt content for a store
     *
     * @param string $content
     * @param int|null $storeId
     * @return int File size in bytes
     * @throws FileSystemException
     */
    public function writeLlmsTxt(string $content, ?int $storeId = null): int
    {
        return $this->atomicWrite(self::LLMS_TXT_FILENAME, $content, $storeId);
    }

    /**
     * Write llms-full.txt content for a store
     *
     * @param string $content
     * @param int|null $storeId
     * @return int File size in bytes
     * @throws FileSystemException
     */
    public function writeFullTxt(string $content, ?int $storeId = null): int
    {
        return $this->atomicWrite(self::LLMS_FULL_TXT_FILENAME, $content, $storeId);
    }

    /**
     * Check if llms.txt exists for current store
     *
     * @return bool
     */
    public function llmsTxtExists(): bool
    {
        return $this->fileExists(self::LLMS_TXT_FILENAME);
    }

    /**
     * Check if llms-full.txt exists for current store
     *
     * @return bool
     */
    public function fullTxtExists(): bool
    {
        return $this->fileExists(self::LLMS_FULL_TXT_FILENAME);
    }

    /**
     * Read llms.txt content for current store
     *
     * @return string
     */
    public function readLlmsTxt(): string
    {
        return $this->readFile(self::LLMS_TXT_FILENAME);
    }

    /**
     * Read llms-full.txt content for current store
     *
     * @return string
     */
    public function readFullTxt(): string
    {
        return $this->readFile(self::LLMS_FULL_TXT_FILENAME);
    }

    /**
     * Get the store-scoped directory path: rkd_llmstxt/{store_code}
     *
     * @param int|null $storeId
     * @return string
     */
    private function getStorePath(?int $storeId = null): string
    {
        $storeCode = $this->resolveStoreCode($storeId);

        return self::STORAGE_DIR . '/' . $storeCode;
    }

    /**
     * Resolve store code from store ID
     *
     * @param int|null $storeId
     * @return string
     */
    private function resolveStoreCode(?int $storeId = null): string
    {
        if ($storeId !== null) {
            return $this->storeManager->getStore($storeId)->getCode();
        }

        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Atomic write: temp file → rename
     *
     * @param string $filename
     * @param string $content
     * @param int|null $storeId
     * @return int File size in bytes
     * @throws FileSystemException
     */
    private function atomicWrite(string $filename, string $content, ?int $storeId = null): int
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $storePath = $this->getStorePath($storeId);
        $filePath = $storePath . '/' . $filename;
        $tempPath = $storePath . '/' . self::TEMP_PREFIX . $filename;

        $directory->create($storePath);

        try {
            $directory->writeFile($tempPath, $content);
            $directory->renameFile($tempPath, $filePath);

            $sizeBytes = strlen($content);

            $this->logger->info(sprintf(
                'RKD LLMs.txt: Wrote %s/%s (%s bytes)',
                $storePath,
                $filename,
                number_format($sizeBytes)
            ));

            return $sizeBytes;
        } catch (FileSystemException $e) {
            if ($directory->isExist($tempPath)) {
                try {
                    $directory->delete($tempPath);
                } catch (FileSystemException $cleanupException) {
                    $this->logger->warning(sprintf(
                        'RKD LLMs.txt: Failed to clean up temp file: %s',
                        $cleanupException->getMessage()
                    ));
                }
            }

            $this->logger->error(sprintf(
                'RKD LLMs.txt: Failed to write %s: %s',
                $filePath,
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Check if a file exists for the current store
     *
     * @param string $filename
     * @return bool
     */
    private function fileExists(string $filename): bool
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $storePath = $this->getStorePath();

        return $directory->isExist($storePath . '/' . $filename);
    }

    /**
     * Read a file for the current store
     *
     * @param string $filename
     * @return string
     */
    private function readFile(string $filename): string
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $storePath = $this->getStorePath();
        $filePath = $storePath . '/' . $filename;

        if (!$directory->isExist($filePath)) {
            return '';
        }

        return $directory->readFile($filePath);
    }
}
