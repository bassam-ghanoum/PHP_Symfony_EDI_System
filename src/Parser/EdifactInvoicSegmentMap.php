<?php

declare(strict_types=1);

namespace App\Parser;

final class EdifactInvoicSegmentMap
{
    public const SEGMENT_BGM = 'BGM';
    public const SEGMENT_MOA = 'MOA';
    public const SEGMENT_TAX = 'TAX';
    public const SEGMENT_RFF = 'RFF';
    public const SEGMENT_LIN = 'LIN';
    public const SEGMENT_QTY = 'QTY';
    public const SEGMENT_PRI = 'PRI';

    public const BGM_INVOICE_TYPE_CODE_POSITION = 1;
    public const BGM_INVOICE_NUMBER_POSITION = 2;
    public const BGM_DATE_POSITION = 3;

    public const DTM_DATE_TYPE_POSITION = 1;
    public const DTM_DATE_VALUE_POSITION = 2;

    public const MOA_AMOUNT_POSITION = 1;
    public const MOA_AMOUNT_VALUE_INDEX = 0;
    public const MOA_TYPE_TOTAL_EXCL_TAX = '79';
    public const MOA_TYPE_TOTAL_INCL_TAX = '9';
    public const MOA_TYPE_TOTAL_INCL_TAX_ALT = '129';
    public const MOA_TYPE_TAX_AMOUNT = '124';
    public const MOA_TYPE_TOTAL_NET = '125';
    public const MOA_TYPE_AMOUNT_ALT = '77';

    public const TAX_TAX_TYPE_POSITION = 1;
    public const TAX_TAX_RATE_POSITION = 2;
    public const TAX_AMOUNT_POSITION = 3;
    public const TAX_AMOUNT_VALUE_INDEX = 0;

    public const LIN_ITEM_NUMBER_POSITION = 2;
    public const SEGMENT_DATA_POSITION = 1;

    public const RFF_REFERENCE_POSITION = 1;
    public const RFF_ORDER_REFERENCE = 'ON';
    public const RFF_DESPATCH_REFERENCE = 'DQ';
    public const RFF_INVOICE_REFERENCE = 'IV';
    public const RFF_VENDOR_REFERENCE = 'VN';
    public const RFF_VAT_REFERENCE = 'VA';

    private function __construct()
    {
    }
}
