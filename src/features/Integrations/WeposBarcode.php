<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class WeposBarcode extends DefaultField
{
    const OPTION_VALUE = 'wepos_barcode';
    const OPTION_NAME = 'Barcode - wePOS - Point Of Sale (POS) for WooCommerce';
    const POST_META_KEY = '_wepos_barcode';
    const PLUGIN_NAME = 'wepos/wepos.php';
}
