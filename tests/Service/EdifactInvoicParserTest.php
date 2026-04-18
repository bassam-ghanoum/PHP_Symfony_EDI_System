<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\EdifactInvoicParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class EdifactInvoicParserTest extends TestCase
{
    private const WORKSPACE_PREFIX = 'edifact-invoic-parser-';
    private const NON_INVOIC_CONTENT = "UNH+3+ORDERS:D:96A:UN'\nBGM+220+12345+9'";
    private const INVOIC_CONTENT = "UNA:+.? '\nUNH+3+INVOIC:D:96A:UN'\nBGM+380+18871152+9'";
    private const NOT_INVOIC_MESSAGE = 'Imported file is not an EDIFACT INVOIC D96A message. Parsing stopped.';
    private const INVOIC_DETECTED_MESSAGE = 'EDIFACT INVOIC D96A message detected. Parsing process started.';
    private const IMPORTED_FILE_MESSAGE_FRAGMENT = 'Imported file';
    private const MISSING_FILE_NAME = 'missing.edi';
    private const IMPORTED_FILE_NAME = 'invoice.txt';

    private string $workspaceDirectory;

    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.self::WORKSPACE_PREFIX.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->workspaceDirectory)) {
            $filesystem->remove($this->workspaceDirectory);
        }
    }

    public function testItStopsWhenImportedFileIsNotAnInvoicMessage(): void
    {
        $filePath = $this->createImportedFile(self::NON_INVOIC_CONTENT);

        $result = (new EdifactInvoicParser())->parse($filePath);

        self::assertFalse($result->isInvoic);
        self::assertSame(self::NOT_INVOIC_MESSAGE, $result->message);
    }

    public function testItRecognizesAnInvoicMessageAndStartsTheProcess(): void
    {
        $filePath = $this->createImportedFile(self::INVOIC_CONTENT);

        $result = (new EdifactInvoicParser())->parse($filePath);

        self::assertTrue($result->isInvoic);
        self::assertSame(self::INVOIC_DETECTED_MESSAGE, $result->message);
    }

    public function testItThrowsWhenTheImportedFileDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::IMPORTED_FILE_MESSAGE_FRAGMENT);

        (new EdifactInvoicParser())->parse($this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::MISSING_FILE_NAME);
    }

    private function createImportedFile(string $content): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->workspaceDirectory);

        $filePath = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.self::IMPORTED_FILE_NAME;
        file_put_contents($filePath, $content);

        return $filePath;
    }
}
