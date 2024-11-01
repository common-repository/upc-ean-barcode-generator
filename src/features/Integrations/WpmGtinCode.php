<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class WpmGtinCode extends DefaultField
{
    const OPTION_VALUE = 'wpm_gtin_code';
    const OPTION_NAME = 'Product GTIN (EAN, UPC, ISBN) for WooCommerce'; 
    const POST_META_KEY = '_wpm_gtin_code';
    const PLUGIN_NAME = 'product-gtin-ean-upc-isbn-for-woocommerce/product-gtin-ean-upc-isbn-for-woocommerce.php';
    public function __construct()
    {
        parent::__construct();

        $this->optionName = get_option('wpm_pgw_label', __('EAN', 'product-gtin-ean-upc-isbn-for-woocommerce'))
            . ' - Product GTIN (EAN, UPC, ISBN) for WooCommerce';
    }
}
