<?php

declare(strict_types=1);

namespace App\Service;

use App\DataObject\Dtos\InvoiceDetailDto;
use App\DataObject\Dtos\InvoiceDto;

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

    // BGM segment: invoice type code is at position 1, invoice number at position 2
    private const BGM_INVOICE_TYPE_CODE_POSITION = 1;
    private const BGM_INVOICE_NUMBER_POSITION = 2;
    private const BGM_DATE_POSITION = 3;

    // DTM segment: date/time information
    private const DTM_DATE_TYPE_POSITION = 1;
    private const DTM_DATE_VALUE_POSITION = 2;

    // MOA segment: monetary amount type codes
    private const MOA_AMOUNT_POSITION = 1;
    private const MOA_AMOUNT_VALUE_INDEX = 0;
    private const MOA_TYPE_TOTAL_EXCL_TAX = '79';
    private const MOA_TYPE_TOTAL_INCL_TAX = '9';
    private const MOA_TYPE_TOTAL_INCL_TAX_ALT = '129';
    private const MOA_TYPE_TAX_AMOUNT = '124';
    private const MOA_TYPE_TOTAL_NET = '125';
    private const MOA_TYPE_AMOUNT_ALT = '77';

    // TAX segment: tax type at position 1, tax rate at position 2, tax amount at position 3
    private const TAX_TAX_TYPE_POSITION = 1;
    private const TAX_TAX_RATE_POSITION = 2;
    private const TAX_AMOUNT_POSITION = 3;
    private const TAX_AMOUNT_VALUE_INDEX = 0;

    // LIN segment: line number at position 1, item number at position 2
    private const LIN_ITEM_NUMBER_POSITION = 2;

    // QTY and PRI often encode their values in the first composite element after the tag.
    private const SEGMENT_DATA_POSITION = 1;

    // RFF segment: reference information - reference types
    private const RFF_REFERENCE_POSITION = 1;
    private const RFF_ORDER_REFERENCE = 'ON';
    private const RFF_DESPATCH_REFERENCE = 'DQ';
    private const RFF_INVOICE_REFERENCE = 'IV';
    private const RFF_VENDOR_REFERENCE = 'VN';
    private const RFF_VAT_REFERENCE = 'VA';

    public function parse(string $content): ?InvoiceDto
    {
        $lines = $this->extractSegments($content);
        
        if (empty($lines)) {
            return null;
        }

        $invoiceData = $this->extractInvoiceHeader($lines);
        $invoiceDetails = $this->extractInvoiceDetails($lines);

        return InvoiceDto::create(
            invoiceNumber: $invoiceData['invoiceNumber'] ?? null,
            orderId: $invoiceData['orderId'] ?? null,
            dispatchId: $invoiceData['dispatchId'] ?? null,
            invoiceType: $invoiceData['invoiceType'] ?? null,
            totalBeforeTax: $invoiceData['totalBeforeTax'] ?? null,
            totalAfterTax: $invoiceData['totalAfterTax'] ?? null,
            taxType: $invoiceData['taxType'] ?? null,
            taxAmount: $invoiceData['taxAmount'] ?? null,
            fileId: null,
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
            'orderId' => null,
            'dispatchId' => null,
            'totalBeforeTax' => null,
            'totalAfterTax' => null,
            'taxType' => null,
            'taxAmount' => null,
        ];

        foreach ($segments as $segment) {
            $elements = explode(self::ELEMENT_SEPARATOR, $segment);
            $tag = $elements[0] ?? '';

            if ($tag === 'BGM') {
                $data['invoiceType'] = $elements[self::BGM_INVOICE_TYPE_CODE_POSITION] ?? null;
                $data['invoiceNumber'] = $elements[self::BGM_INVOICE_NUMBER_POSITION] ?? null;
            }

            if ($tag === 'MOA') {
                $amount = $elements[self::MOA_AMOUNT_POSITION] ?? '';
                if ($amount) {
                    $components = explode(self::COMPONENT_SEPARATOR, $amount);
                    $moaType = $components[0] ?? '';
                    $moaAmount = $components[1] ?? '';
                    
                    // Handle different MOA type codes for totals
                    if ($moaType === self::MOA_TYPE_TOTAL_EXCL_TAX) {
                        $data['totalBeforeTax'] = $this->formatDecimal($moaAmount);
                    } elseif ($moaType === self::MOA_TYPE_TOTAL_INCL_TAX || $moaType === self::MOA_TYPE_TOTAL_INCL_TAX_ALT) {
                        $data['totalAfterTax'] = $this->formatDecimal($moaAmount);
                    } elseif ($moaType === self::MOA_TYPE_TAX_AMOUNT) {
                        $data['taxAmount'] = $this->formatDecimal($moaAmount);
                    }
                }
            }

            if ($tag === 'TAX') {
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

            if ($tag === 'RFF') {
                $reference = $elements[self::RFF_REFERENCE_POSITION] ?? '';
                if ($reference) {
                    $components = explode(self::COMPONENT_SEPARATOR, $reference);
                    $refType = $components[0] ?? '';
                    $refValue = $components[1] ?? '';
                    
                    if ($refType === self::RFF_ORDER_REFERENCE) {
                        $data['orderId'] = is_numeric($refValue) ? (int) $refValue : null;
                    } elseif ($refType === self::RFF_DESPATCH_REFERENCE) {
                        $data['dispatchId'] = is_numeric($refValue) ? (int) $refValue : null;
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

            if ($tag === 'LIN') {
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
                if ($tag === 'QTY') {
                    $quantity = $elements[self::SEGMENT_DATA_POSITION] ?? '';
                    if ($quantity) {
                        $components = explode(self::COMPONENT_SEPARATOR, $quantity);
                        // Common formats include "47:1:EA" and "1:100".
                        $qty = $components[1] ?? $components[0] ?? '';
                        $currentLineItem['quantity'] = is_numeric($qty) 
                            ? (int) $qty 
                            : null;
                    }
                }

                if ($tag === 'PRI') {
                    $price = $elements[self::SEGMENT_DATA_POSITION] ?? '';
                    if ($price) {
                        $components = explode(self::COMPONENT_SEPARATOR, $price);
                        // Only set unit price if we haven't already, to preserve the first/best price
                        if ($currentLineItem['unitPrice'] === null) {
                            $currentLineItem['unitPrice'] = $this->formatDecimal($components[1] ?? $components[0] ?? '');
                        }
                    }
                }

                if ($tag === 'MOA') {
                    $amount = $elements[self::MOA_AMOUNT_POSITION] ?? '';
                    if ($amount) {
                        $components = explode(self::COMPONENT_SEPARATOR, $amount);
                        $moaType = $components[0] ?? '';
                        $moaAmount = $components[1] ?? '';
                        
                        if ($moaType === self::MOA_TYPE_TOTAL_INCL_TAX || $moaType === self::MOA_TYPE_TOTAL_INCL_TAX_ALT) {
                            $currentLineItem['totalAfterTax'] = $this->formatDecimal($moaAmount);
                        } elseif ($moaType === self::MOA_TYPE_TOTAL_EXCL_TAX) {
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
