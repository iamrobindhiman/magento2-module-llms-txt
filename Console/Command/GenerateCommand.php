<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use RKD\LlmsTxt\Api\LlmsTxtGeneratorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command: bin/magento rkd:llmstxt:generate
 */
class GenerateCommand extends Command
{
    private const OPTION_STORE = 'store';
    private const OPTION_TYPE = 'type';
    private const OPTION_VALIDATE = 'validate';
    private const OPTION_DRY_RUN = 'dry-run';

    public function __construct(
        private readonly LlmsTxtGeneratorInterface $generator,
        private readonly State $appState,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('rkd:llmstxt:generate')
            ->setDescription('Generate llms.txt and/or llms-full.txt files')
            ->addOption(
                self::OPTION_STORE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Store ID (default: default store)'
            )
            ->addOption(
                self::OPTION_TYPE,
                null,
                InputOption::VALUE_OPTIONAL,
                'File type: llms_txt, llms_full_txt, or both',
                'both'
            )
            ->addOption(
                self::OPTION_VALIDATE,
                null,
                InputOption::VALUE_NONE,
                'Run validation after generation'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Preview output without writing files'
            );

        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set — safe to continue
        }

        $storeId = $input->getOption(self::OPTION_STORE);
        $storeId = $storeId !== null ? (int) $storeId : null;
        $isDryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $forceValidate = (bool) $input->getOption(self::OPTION_VALIDATE);

        $output->writeln('<info>RKD LLMs.txt Generator</info>');
        $output->writeln('');

        if ($isDryRun) {
            $output->writeln('<comment>DRY RUN — preview only, no files will be written</comment>');
            $output->writeln('');

            $preview = $this->generator->preview($storeId);
            $output->writeln($preview);

            return Command::SUCCESS;
        }

        $output->writeln('Generating llms.txt files...');
        $result = $this->generator->generate($storeId, 'cli');

        if (!$result->isSuccess()) {
            $output->writeln('<error>Generation failed!</error>');
            foreach ($result->getValidationErrors() as $error) {
                $output->writeln('  <error>• ' . $error . '</error>');
            }
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(sprintf('  Sections:  %d', $result->getSectionsCount()));
        $output->writeln(sprintf('  Products:  %d', $result->getProductsCount()));
        $output->writeln(sprintf('  File size: %s', $this->formatBytes($result->getFileSizeBytes())));
        $output->writeln(sprintf('  Duration:  %.2f seconds', $result->getDurationSeconds()));
        $output->writeln(sprintf('  Files:     %s', $result->getFileType()));

        $validationErrors = $result->getValidationErrors();
        if (!empty($validationErrors)) {
            $output->writeln('');
            $output->writeln('<comment>Validation warnings:</comment>');
            foreach ($validationErrors as $error) {
                $output->writeln('  <comment>• ' . $error . '</comment>');
            }
        }

        if ($forceValidate && empty($validationErrors)) {
            $errors = $this->generator->validate($storeId);
            if (empty($errors)) {
                $output->writeln('');
                $output->writeln('<info>Validation: PASSED</info>');
            } else {
                $output->writeln('');
                $output->writeln('<comment>Validation issues:</comment>');
                foreach ($errors as $error) {
                    $output->writeln('  <comment>• ' . $error . '</comment>');
                }
            }
        }

        $output->writeln('');
        $output->writeln('<info>Generation complete.</info>');

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
