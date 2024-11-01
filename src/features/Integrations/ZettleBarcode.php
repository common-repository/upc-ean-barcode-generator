<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class ZettleBarcode extends DefaultField
{
    const OPTION_VALUE = 'zettle_barcode';
    const OPTION_NAME = 'Barcode - PayPal Zettle POS';
    const POST_META_KEY = '_zettle_barcode';
    const PLUGIN_NAME = 'zettle-pos-integration/zettle-pos-integration.php';
}
