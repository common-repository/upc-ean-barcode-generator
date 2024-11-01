<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

use UkrSolution\UpcEanGenerator\features\Integrations\DefaultField;

class SkuWooCommerce extends DefaultField
{
    const OPTION_VALUE = 'woocommercesku';
    const OPTION_NAME = 'SKU - WooCommerce';
    const POST_META_KEY = '_sku';
    const INPUT_NAME = '_sku';

    public function getCodeInputName($post)
    {
        if ('product' === $post->post_type) {
            return 'input[name="_sku"]';
        } elseif('product_variation' === $post->post_type) {
            return '.woocommerce_variation:first-child input[name^="variable_sku"]';
        } else {
            return '';
        }
    }
}
