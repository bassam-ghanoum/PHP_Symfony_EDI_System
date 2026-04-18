<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\EdifactInvoicParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class EdifactInvoicParserTest extends TestCase
{
    private string $workspaceDirectory;

    protected function setUp(): void
    {
        $this->workspaceDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'edifact-invoic-parser-'.bin2hex(random_bytes(8));
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
        $filePath = $this->createImportedFile("UNH+3+ORDERS:D:96A:UN'\nBGM+220+12345+9'");

        $result = (new EdifactInvoicParser())->parse($filePath);

        self::assertFalse($result->isInvoic);
        self::assertSame('Imported file is not an EDIFACT INVOIC D96A message. Parsing stopped.', $result->message);
    }

    public function testItRecognizesAnInvoicMessageAndStartsTheProcess(): void
    {
        $filePath = $this->createImportedFile("UNA:+.? '\nUNH+3+INVOIC:D:96A:UN'\nBGM+380+18871152+9'");

        $result = (new EdifactInvoicParser())->parse($filePath);

        self::assertTrue($result->isInvoic);
        self::assertSame('EDIFACT INVOIC D96A message detected. Parsing process started.', $result->message);
    }

    private function createImportedFile(string $content): string
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->workspaceDirectory);

        $filePath = $this->workspaceDirectory.\DIRECTORY_SEPARATOR.'invoice.txt';
        file_put_contents($filePath, $content);

        return $filePath;
    }
}
