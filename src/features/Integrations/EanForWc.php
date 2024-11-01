<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class EanForWc extends DefaultField
{
    const OPTION_VALUE = 'ean_for_wc';
    const OPTION_NAME = 'EAN - EAN for WooCommerce';
    const POST_META_KEY = '_alg_ean';
    const PLUGIN_NAME = 'ean-for-woocommerce/ean-for-woocommerce.php';

    public function __construct()
    {
        parent::__construct();

        $this->optionName = get_option('alg_wc_ean_title', esc_html__('EAN', 'ean-for-woocommerce'))
            . ' - EAN for WooCommerce';
    }
}
