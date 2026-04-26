<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImportedFileMover;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: MoveImportedFilesCommand::COMMAND_NAME,
    description: MoveImportedFilesCommand::COMMAND_DESCRIPTION,
)]
final class MoveImportedFilesCommand extends Command
{
    public const COMMAND_NAME = 'app:imported-files:move';
    public const COMMAND_DESCRIPTION = 'Moves supported imported files from /ImportedFiles/input to /ImportedFiles/imported.';

    public function __construct(
        private ImportedFileMover $importedFileMover,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $successCount = 0;
        $failureCount = 0;
        $movedFiles = [];

        foreach (($this->importedFileMover)() as $result) {
            if ($result->status === 'processing') {
                $io->writeln(\sprintf('Processing file: <info>%s</info>', $result->fileName));
            } elseif ($result->status === 'success') {
                $io->writeln(\sprintf('✓ Successfully imported: <info>%s</info>', $result->fileName));
                ++$successCount;
                $movedFiles[] = $result->fileName;
            } elseif ($result->status === 'failed') {
                $io->writeln(\sprintf('✗ Failed to import: <error>%s</error> - %s', $result->fileName, $result->errorMessage));
                ++$failureCount;
            }
        }

        $io->newLine();
        $io->success(\sprintf(
            'Imported %d file(s), failed %d file(s)',
            $successCount,
            $failureCount,
        ));

        if ($movedFiles !== []) {
            $io->listing($movedFiles);
        }

        return Command::SUCCESS;
    }
}
