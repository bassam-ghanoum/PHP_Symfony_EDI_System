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
    private const SUCCESS_MESSAGE = 'Processed %d file(s) from "%s" to "%s".';

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
            self::SUCCESS_MESSAGE,
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
