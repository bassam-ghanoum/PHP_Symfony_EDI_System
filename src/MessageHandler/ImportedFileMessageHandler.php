<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportedFileMessage;
use App\Service\EdifactInvoicParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImportedFileMessageHandler
{
    public function __construct(
        private EdifactInvoicParser $edifactInvoicParser,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportedFileMessage $message): void
    {
        $result = $this->edifactInvoicParser->parse($message->filePath);

        if (!$result->isInvoic) {
            $this->logger->info($result->message, [
                'file_path' => $result->filePath,
            ]);

            return;
        }

        $this->logger->info($result->message, [
            'file_path' => $result->filePath,
            'next_step' => 'Continue INVOIC parsing implementation.',
        ]);
    }
}
