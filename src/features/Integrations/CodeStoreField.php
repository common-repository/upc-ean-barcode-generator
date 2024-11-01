<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

abstract class CodeStoreField
{
    const OPTION_VALUE = '';
    const OPTION_NAME = '';
    const INPUT_NAME = '';
    const PLUGIN_NAME = '';
    const POST_TYPES = array('product', 'product_variation');
    const POST_STATUSES = array('publish', 'pending', 'draft', 'future', 'private', 'inherit', 'trash');

    public $optionName = '';
    protected static $instances = array();

    public static function register()
    {
        if (empty(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
        }
    }

    protected function __construct()
    {
        if (!static::isActive()) {
            return null;
        }

        $this->optionName = __(static::OPTION_NAME, 'upc-ean-generator');

        add_filter('uegen_code_store_field_options', function ($codeStoreFieldOptions) {
            if (is_array($codeStoreFieldOptions)) {
                $codeStoreFieldOptions[] = array('value' => static::OPTION_VALUE, 'name' => $this->optionName);
            } else {
                $codeStoreFieldOptions = array(array('value' => static::OPTION_VALUE, 'name' => $this->optionName));
            }

            return $codeStoreFieldOptions;
        });

        add_filter('uegen_get_product_code_field_value', function($value, $integrationOptionValue, $post){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $value = static::getCodeValue($post);
            }

            return $value;
        }, 10, 3);

        add_filter('uegen_get_product_code_input_name', function($value, $integrationOptionValue, $post){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $value = static::getCodeInputName($post);
            }

            return $value;
        }, 10, 3);

        add_action('uegen_set_product_code_field_value', function($integrationOptionValue, $post, $value, &$result, &$usedMetaKey = ''){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $result = static::setCodeValue($post, $value, $usedMetaKey);
            }
        }, 10, 5);

        add_action('uegen_unset_product_code_field_value', function($integrationOptionValue, $codeRow, &$errorMessage){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $errorMessage = static::unsetCodeValue($codeRow);
            }
        }, 10, 3);

        add_action('uegen_check_if_code_value_not_used', function($integrationOptionValue, $post, $value, &$result){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $result = static::codeValueNotUsed($value);
            }
        }, 10, 4);

        add_filter('uegen_get_empty_codes_products', function($productsArr, $integrationOptionValue, $offset = 0, $limit = -1){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $productsArr = static::getEmptyCodesProducts($offset, $limit);
            }

            return $productsArr;
        }, 10, 4);

        add_filter('uegen_get_all_products_query', function($query, $integrationOptionValue, $offset = 0, $limit = 0){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $query = static::getAllProductsQuery($offset, $limit);
            }

            return $query;
        }, 10, 4);

        add_filter('uegen_get_empty_codes_products_query', function($query, $integrationOptionValue, $offset = 0, $limit = 0){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $query = static::getEmptyCodesProductsQuery($offset, $limit);
            }

            return $query;
        }, 10, 4);

        add_filter('uegen_add_search_query_params', function($search, $query, $searchStr, $integrationOptionValue){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $search = static::addSearchQueryParams($search, $query, $searchStr);
            }

            return $search;
        }, 10, 4);

        add_filter('uegen_add_search_query_tables', function($query, $searchStr, $integrationOptionValue){
            if (static::OPTION_VALUE === $integrationOptionValue) {
                $query = static::addSearchQueryTables($query, $searchStr);
            }

            return $query;
        }, 10, 4);
    }

    public function isActive()
    {
        return !function_exists('is_plugin_active')
            ? (empty(static::PLUGIN_NAME) ||  in_array(static::PLUGIN_NAME, (array)get_option('active_plugins', array())))
            : (empty(static::PLUGIN_NAME) || is_plugin_active(static::PLUGIN_NAME));
    }

    abstract public function getCodeValue($post);

    public function getCodeInputName($post)
    {
        return static::INPUT_NAME;
    }

    abstract public function setCodeValue($post, $value, &$usedMetaKey);

    abstract public function unsetCodeValue($codeRow);

    abstract public function codeValueNotUsed($value);

    public function getEmptyCodesProducts($offset = 0, $limit = -1)
    {
        return array();
    }

    public function getEmptyCodesProductsQuery($offset = 0, $limit = 0)
    {
        return new \WP_Query();
    }

    public function getAllProductsQuery($offset = 0, $limit = 0)
    {
        $args = array(
            'post_type' => 'product',
            'post_status' => static::POST_STATUSES,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                'relation' => 'OR',
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'simple',
                ),
                array(
                    'taxonomy' => 'product_type',
                    'operator' => 'NOT EXISTS',
                ),
            ),
            'suppress_filters' => true,
            'lang' => 'all',
        );

        $simpleProductsIds = (new \WP_Query($args))->posts;

        unset($args['tax_query']);
        $args['post_type'] = 'product_variation';
        $args['fields'] = 'id=>parent';
        $productsVariations = (new \WP_Query($args))->posts;
        $productsVariationsParentsIds = wp_list_pluck($productsVariations, 'post_parent');

        $actualProductsIds = (new \WP_Query(array(
            'post_type' => 'product',
            'post_status' => static::POST_STATUSES,
            'post__in' => empty($productsVariationsParentsIds) ? array(0) : array_values($productsVariationsParentsIds),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
            'lang' => 'all',
        )))->posts;

        $args['post_parent__in'] = empty($actualProductsIds) ? array(0) : array_values($actualProductsIds);
        $args['fields'] = 'ids';
        $variationsIds = (new \WP_Query($args))->posts;

        $postsIds = array_merge($simpleProductsIds, $variationsIds);
        $args = array(
            'post_type' => static::POST_TYPES,
            'post_status' => static::POST_STATUSES,
            'post__in' => empty($postsIds) ? array(0) : array_values($postsIds),
            'offset' => $offset,
            'posts_per_page' => $limit,
            'suppress_filters' => true,
            'lang' => 'all',
        );

        $result = new \WP_Query($args);
        return $result;

    }

    public function addSearchQueryParams($search, $query, $searchStr)
    {
        return $search;
    }

    public function addSearchQueryTables($query, $searchStr)
    {
        return $query;
    }
}
