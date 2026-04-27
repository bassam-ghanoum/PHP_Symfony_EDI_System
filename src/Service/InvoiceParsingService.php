<?php

declare(strict_types=1);

namespace App\Service;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\DataObject\Dtos\IncomingFileDto;
use App\DataObject\Dtos\InvoiceDto;
use App\Parser\EdifactInvoicSegmentMap;
use App\Repository\OrderRepository;
use App\Repository\DispatchRepository;

/**
 * Service for parsing EDIFACT INVOIC D96A messages.
 *
 * INVOIC (Invoice message) structure according to UN/EDIFACT D96A:
 * - UNA: Service string advice (delimiters)
 * - UNH: Message header
 * - BGM: Beginning of message (invoice number, type, date)
 * - DTM: Date/time information
 * - NAD: Name and address
 * - LIN: Line item identification
 * - QTY: Quantity
 * - PRI: Pricing information
 * - MOA: Monetary amounts
 * - TAX: Tax information
 * - UNS: Message control total
 * - UNT: Message trailer
 */
final class InvoiceParsingService
{
    private const UNA_LENGTH = 9;
    private const SEGMENT_DELIMITER = "'";
    private const ELEMENT_SEPARATOR = '+';
    private const COMPONENT_SEPARATOR = ':';
    private const DECIMAL_SEPARATOR = ',';

    private OrderRepository $orderRepository;
    private DispatchRepository $dispatchRepository;

    public function __construct(
        OrderRepository $orderRepository,
        DispatchRepository $dispatchRepository,
    ) {
        $this->orderRepository = $orderRepository;
        $this->dispatchRepository = $dispatchRepository;
    }

    public function parse(string $content, IncomingFileDto $incomingFile): ?InvoiceDto
    {
        $lines = $this->extractSegments($content);
        
        if (empty($lines)) {
            return null;
        }

        $invoiceData = $this->extractInvoiceHeader($lines);
        $invoiceDetails = $this->extractInvoiceDetails($lines);

        // Look up actual order record by order_number
        $orderId = null;
        if ($invoiceData['orderNumber'] !== null) {
            $order = $this->orderRepository->findByOrderNumber((string)$invoiceData['orderNumber']);
            $orderId = $order?->getId();
        }

        // Look up actual dispatch record by dispatcher_number
        $dispatchId = null;
        if ($invoiceData['dispatchNumber'] !== null) {
            $dispatch = $this->dispatchRepository->findByDispatcherNumberAndOrderNumber(
                (string)$invoiceData['dispatchNumber'],
                (string)$invoiceData['orderNumber']
                );
            $dispatchId = $dispatch?->getId();
        }

        return InvoiceDto::create(
            invoiceNumber: $invoiceData['invoiceNumber'] ?? null,
            orderId: $orderId,
            dispatchId: $dispatchId,
            invoiceType: $invoiceData['invoiceType'] ?? null,
            totalBeforeTax: $invoiceData['totalBeforeTax'] ?? null,
            totalAfterTax: $invoiceData['totalAfterTax'] ?? null,
            taxType: $invoiceData['taxType'] ?? null,
            taxAmount: $invoiceData['taxAmount'] ?? null,
            incomingFile: $incomingFile,
            fileReceivedDate: new \DateTimeImmutable(),
            details: $invoiceDetails,
        );
    }

    /**
     * @return string[]
     */
    private function extractSegments(string $content): array
    {
        // Remove UNA if present
        if (substr($content, 0, 3) === 'UNA') {
            $content = substr($content, self::UNA_LENGTH);
        }

        // Split by segment delimiter
        $segments = explode(self::SEGMENT_DELIMITER, $content);
        
        return array_filter(
            array_map('trim', $segments),
            static fn ($segment) => $segment !== ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInvoiceHeader(array $segments): array
    {
        $data = [
            'invoiceNumber' => null,
            'invoiceType' => null,
            'orderNumber' => null,
            'dispatchNumber' => null,
            'totalBeforeTax' => null,
            'totalAfterTax' => null,
            'taxType' => null,
            'taxAmount' => null,
        ];

        foreach ($segments as $segment) {
            $elements = explode(self::ELEMENT_SEPARATOR, $segment);
            $tag = $elements[0] ?? '';

            if ($tag === EdifactInvoicSegmentMap::SEGMENT_BGM) {
                $typeCode = $elements[EdifactInvoicSegmentMap::BGM_INVOICE_TYPE_CODE_POSITION] ?? null;
                $data['invoiceType'] = match ($typeCode) {
                    '380' => 'Debit',
                    '381' => 'Credit',
                    default => $typeCode,
                };
                $data['invoiceNumber'] = $elements[EdifactInvoicSegmentMap::BGM_INVOICE_NUMBER_POSITION] ?? null;
            }

            if ($tag === EdifactInvoicSegmentMap::SEGMENT_MOA) {
                $amount = $elements[EdifactInvoicSegmentMap::MOA_AMOUNT_POSITION] ?? '';
                if ($amount) {
                    $components = explode(self::COMPONENT_SEPARATOR, $amount);
                    $moaType = $components[0] ?? '';
                    $moaAmount = $components[1] ?? '';
                    
                    // Handle different MOA type codes for totals
                    if ($moaType === EdifactInvoicSegmentMap::MOA_TYPE_TOTAL_EXCL_TAX) {
                        $data['totalBeforeTax'] = $this->formatDecimal($moaAmount);
                    } elseif (
                        $moaType === EdifactInvoicSegmentMap::MOA_TYPE_TOTAL_INCL_TAX
                        || $moaType === EdifactInvoicSegmentMap::MOA_TYPE_TOTAL_INCL_TAX_ALT
                    ) {
                        $data['totalAfterTax'] = $this->formatDecimal($moaAmount);
                    } elseif ($moaType === EdifactInvoicSegmentMap::MOA_TYPE_TAX_AMOUNT) {
                        $data['taxAmount'] = $this->formatDecimal($moaAmount);
                    }
                }
            }

            if ($tag === EdifactInvoicSegmentMap::SEGMENT_TAX) {
                // TAX segment formats:
                // Simple: TAX+7+VAT (code + category)
                // Composite: TAX+VAT:20,00:750,63 (VAT:rate:amount)
                
                // First check if we have a composite format with ':'
                if (isset($elements[1]) && strpos($elements[1], self::COMPONENT_SEPARATOR) !== false) {
                    // Composite format: TAX+VAT:rate:amount
                    $components = explode(self::COMPONENT_SEPARATOR, $elements[1]);
                    $data['taxType'] = $components[0] ?? null;
                    if (isset($components[2])) {
                        $data['taxAmount'] = $this->formatDecimal($components[2]);
                    }
                } elseif (isset($elements[2])) {
                    // Simple format: TAX+code+category
                    // elements[1] is the code (7), elements[2] is the category (VAT)
                    $data['taxType'] = $elements[2];
                }
            }

            if ($tag === EdifactInvoicSegmentMap::SEGMENT_RFF) {
                $reference = $elements[EdifactInvoicSegmentMap::RFF_REFERENCE_POSITION] ?? '';
                if ($reference) {
                    $components = explode(self::COMPONENT_SEPARATOR, $reference);
                    $refType = $components[0] ?? '';
                    $refValue = $components[1] ?? '';
                    
                    if ($refType === EdifactInvoicSegmentMap::RFF_ORDER_REFERENCE) {
                        $data['orderNumber'] = is_numeric($refValue) ? (int) $refValue : null;
                    } elseif ($refType === EdifactInvoicSegmentMap::RFF_DESPATCH_REFERENCE) {
                        $data['dispatchNumber'] = is_numeric($refValue) ? (int) $refValue : null;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @return InvoiceDetailDto[]
     */
    private function extractInvoiceDetails(array $segments): array
    {
        $details = [];
        $currentLineItem = null;

        foreach ($segments as $segment) {
            $elements = explode(self::ELEMENT_SEPARATOR, $segment);
            $tag = $elements[0] ?? '';

            if ($tag === EdifactInvoicSegmentMap::SEGMENT_LIN) {
                if ($currentLineItem !== null) {
                    $details[] = $currentLineItem;
                }
                
                // LIN segment format can be:
                // LIN+lineNum+itemTypeCode+itemId (all fields)
                // LIN+lineNum++itemId (itemTypeCode empty)
                // LIN+lineNum+itemId (no itemTypeCode)
                
                $itemNumber = '';
                
                // Try position 2 first (usually has the item code)
                if (!empty($elements[2])) {
                    $itemNumber = $this->extractItemCode($elements[2]);
                }
                
                // If position 2 is empty or doesn't contain what we need, try position 3
                if (empty($itemNumber) && !empty($elements[3])) {
                    $itemNumber = $this->extractItemCode($elements[3]);
                }

                $currentLineItem = [
                    'itemCode' => $itemNumber,
                    'itemDescription' => null,
                    'quantity' => null,
                    'unitPrice' => null,
                    'totalBeforeTax' => null,
                    'totalAfterTax' => null,
                ];
            }

            if ($currentLineItem !== null) {
                if ($tag === EdifactInvoicSegmentMap::SEGMENT_QTY) {
                    $quantity = $elements[EdifactInvoicSegmentMap::SEGMENT_DATA_POSITION] ?? '';
                    if ($quantity) {
                        $components = explode(self::COMPONENT_SEPARATOR, $quantity);
                        // Common formats include "47:1:EA" and "1:100".
                        $qty = $components[1] ?? $components[0] ?? '';
                        $currentLineItem['quantity'] = is_numeric($qty) 
                            ? (int) $qty 
                            : null;
                    }
                }

                if ($tag === EdifactInvoicSegmentMap::SEGMENT_PRI) {
                    $price = $elements[EdifactInvoicSegmentMap::SEGMENT_DATA_POSITION] ?? '';
                    if ($price) {
                        $components = explode(self::COMPONENT_SEPARATOR, $price);
                        // Only set unit price if we haven't already, to preserve the first/best price
                        if ($currentLineItem['unitPrice'] === null) {
                            $currentLineItem['unitPrice'] = $this->formatDecimal($components[1] ?? $components[0] ?? '');
                        }
                    }
                }

                if ($tag === EdifactInvoicSegmentMap::SEGMENT_MOA) {
                    $amount = $elements[EdifactInvoicSegmentMap::MOA_AMOUNT_POSITION] ?? '';
                    if ($amount) {
                        $components = explode(self::COMPONENT_SEPARATOR, $amount);
                        $moaType = $components[0] ?? '';
                        $moaAmount = $components[1] ?? '';
                        
                        if (
                            $moaType === EdifactInvoicSegmentMap::MOA_TYPE_TOTAL_INCL_TAX
                            || $moaType === EdifactInvoicSegmentMap::MOA_TYPE_TOTAL_INCL_TAX_ALT
                        ) {
                            $currentLineItem['totalAfterTax'] = $this->formatDecimal($moaAmount);
                        } elseif ($moaType === EdifactInvoicSegmentMap::MOA_TYPE_TOTAL_EXCL_TAX) {
                            $currentLineItem['totalBeforeTax'] = $this->formatDecimal($moaAmount);
                        }
                    }
                }

                // Add more detail extraction as needed (e.g., IMD for item description)
            }
        }

        if ($currentLineItem !== null) {
            $details[] = $currentLineItem;
        }

        return array_map(
            static fn (array $detail) => InvoiceDetailDto::create(
                itemCode: $detail['itemCode'],
                itemDescription: $detail['itemDescription'],
                quantity: $detail['quantity'],
                unitPrice: $detail['unitPrice'],
                totalBeforeTax: $detail['totalBeforeTax'],
                totalAfterTax: $detail['totalAfterTax'],
            ),
            $details
        );
    }

    private function formatDecimal(string $value): ?string
    {
        if (!$value) {
            return null;
        }

        // Replace EDIFACT decimal separator (comma) with PHP decimal separator (period)
        $value = str_replace(self::DECIMAL_SEPARATOR, '.', $value);
        
        return is_numeric($value) ? $value : null;
    }

    private function extractItemCode(string $itemCodeString): string
    {
        if (empty($itemCodeString)) {
            return '';
        }

        // Item code might be composite (e.g., "100100:SA")
        // Extract the first component
        $components = explode(self::COMPONENT_SEPARATOR, $itemCodeString);
        return $components[0] ?? '';
    }
}
