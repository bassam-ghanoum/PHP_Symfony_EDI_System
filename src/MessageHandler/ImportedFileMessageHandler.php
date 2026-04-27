<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\MessagingException;
use App\Message\ImportedFileMessage;
use App\Parser\EdifactInvoicParserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ImportedFileMessageHandler
{
    private const FILE_PATH_CONTEXT_KEY = 'file_path';
    private const NEXT_STEP_CONTEXT_KEY = 'next_step';
    private const NEXT_STEP_MESSAGE = 'Continue INVOIC data processing and persistence.';
    private const INVOICE_NUMBER_CONTEXT_KEY = 'invoice_number';
    private const INVOICE_DETAILS_CONTEXT_KEY = 'invoice_details_count';

    public function __construct(
        private EdifactInvoicParserInterface $edifactInvoicParser,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportedFileMessage $message): void
    {
        try {
            $result = $this->edifactInvoicParser->parse($message->filePath);

            if (!$result->isInvoic) {
                $this->logger->info($result->message, [
                    self::FILE_PATH_CONTEXT_KEY => $result->filePath,
                ]);

                return;
            }

            $context = [
                self::FILE_PATH_CONTEXT_KEY => $result->filePath,
                self::NEXT_STEP_CONTEXT_KEY => self::NEXT_STEP_MESSAGE,
            ];
            if ($result->invoice !== null) {
                $context[self::INVOICE_NUMBER_CONTEXT_KEY] = $result->invoice->invoiceNumber;
                $context[self::INVOICE_DETAILS_CONTEXT_KEY] = count($result->invoice->details);
            }

            $this->logger->info($result->message, $context);
        } catch (MessagingException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new MessagingException(
                \sprintf('Unable to handle imported file message for "%s".', $message->filePath),
                previous: $throwable,
            );
        }
    }
}
