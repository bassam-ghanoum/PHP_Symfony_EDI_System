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
    name: 'app:imported-files:move',
    description: 'Moves files from /ImportedFiles/input to /ImportedFiles/imported.',
)]
final class MoveImportedFilesCommand extends Command
{
    public function __construct(
        private readonly ImportedFileMover $importedFileMover,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->importedFileMover->moveFiles();

        $io->success(\sprintf(
            'Processed %d file(s) from "%s" to "%s".',
            $result->movedCount(),
            $result->inputDirectory,
            $result->importedDirectory,
        ));

        if ($result->movedFiles !== []) {
            $io->listing($result->movedFiles);
        }

        return Command::SUCCESS;
    }
}
