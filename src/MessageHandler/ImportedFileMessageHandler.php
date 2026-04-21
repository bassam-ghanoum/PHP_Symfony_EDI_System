<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportedFileMessage;
use App\Parser\EdifactInvoicParserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ImportedFileMessageHandler
{
    private const FILE_PATH_CONTEXT_KEY = 'file_path';
    private const NEXT_STEP_CONTEXT_KEY = 'next_step';
    private const NEXT_STEP_MESSAGE = 'Continue INVOIC parsing implementation.';

    public function __construct(
        private EdifactInvoicParserInterface $edifactInvoicParser,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportedFileMessage $message): void
    {
        $result = $this->edifactInvoicParser->parse($message->filePath);

        if (!$result->isInvoic) {
            $this->logger->info($result->message, [
                self::FILE_PATH_CONTEXT_KEY => $result->filePath,
            ]);

            return;
        }

        $this->logger->info($result->message, [
            self::FILE_PATH_CONTEXT_KEY => $result->filePath,
            self::NEXT_STEP_CONTEXT_KEY => self::NEXT_STEP_MESSAGE,
        ]);
    }
}
