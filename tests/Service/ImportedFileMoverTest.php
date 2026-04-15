<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ImportedFileMover;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ImportedFileMoverTest extends TestCase
{
    private string $workspaceDirectory;

    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'imported-file-mover-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->workspaceDirectory)) {
            $filesystem->remove($this->workspaceDirectory);
        }
    }

    public function testItCreatesDirectoriesAndMovesFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.'input';

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'first.txt', 'first');
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'second.txt', 'second');

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(2, $result->movedCount());
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.'first.txt');
        self::assertFileDoesNotExist($result->inputDirectory.\DIRECTORY_SEPARATOR.'second.txt');
        self::assertFileExists($result->importedDirectory.\DIRECTORY_SEPARATOR.'first.txt');
        self::assertFileExists($result->importedDirectory.\DIRECTORY_SEPARATOR.'second.txt');
    }

    public function testItIgnoresNonTxtFiles(): void
    {
        $filesystem = new Filesystem();
        $inputDirectory = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.'input';

        $filesystem->mkdir($inputDirectory);
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'note.txt', 'text');
        file_put_contents($inputDirectory.\DIRECTORY_SEPARATOR.'image.jpeg', 'binary');

        $mover = new ImportedFileMover(
            filesystem: $filesystem,
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(1, $result->movedCount());
        self::assertSame(['note.txt'], $result->movedFiles);
        self::assertFileExists($result->importedDirectory.\DIRECTORY_SEPARATOR.'note.txt');
        self::assertFileExists($result->inputDirectory.\DIRECTORY_SEPARATOR.'image.jpeg');
    }

    public function testItCreatesMissingDirectoriesWhenTheyDoNotExist(): void
    {
        $mover = new ImportedFileMover(
            filesystem: new Filesystem(),
            baseDirectory: $this->workspaceDirectory,
        );

        $result = $mover->moveFiles();

        self::assertSame(0, $result->movedCount());
        self::assertDirectoryExists($result->inputDirectory);
        self::assertDirectoryExists($result->importedDirectory);
    }
}
