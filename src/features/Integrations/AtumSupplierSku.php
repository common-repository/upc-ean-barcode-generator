<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class AtumSupplierSku extends CodeStoreField
{
    const OPTION_VALUE = 'atum_supplier_sku';
    const OPTION_NAME = "Supplier's SKU - ATUM Inventory Management for WooCommerce";
    const PLUGIN_NAME = 'atum-stock-manager-for-woocommerce/atum-stock-manager-for-woocommerce.php';

    public function getCodeValue($post)
    {
        global $wpdb;

        $postId = is_a($post, \WP_Post::class) ? $post->ID : (int)$post;
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $postId));

        return !empty($record) && isset($record->supplier_sku)
            ? $record->supplier_sku
            : "";
    }

    public function setCodeValue($post, $value, &$usedMetaKey)
    {
        global $wpdb;

        $usedMetaKey = "supplier_sku";
        $postId = is_a($post, \WP_Post::class) ? $post->ID : (int)$post;

        $productRow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $postId ), ARRAY_A );

        if (empty($productRow)) {
            $result = $wpdb->insert("{$wpdb->prefix}atum_product_data", array("product_id" => $postId, "supplier_sku" => $value));
        } else {
            $result = $value === ""
                ? $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}atum_product_data SET supplier_sku = null WHERE product_id = %d", $postId))
                : $wpdb->update("{$wpdb->prefix}atum_product_data", array("supplier_sku" => $value), array("product_id" => $postId));

        }

        return !empty($result);
    }

    public function unsetCodeValue($codeRow)
    {
        global $wpdb;
        $errorMessage = "";

        if (!empty(intval($codeRow->product_id))) {
            $productRow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}atum_product_data WHERE product_id = %d;", $codeRow->product_id ), ARRAY_A );
        } else {
            $productRow = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}atum_product_data WHERE supplier_sku = %d;", $codeRow->code ), ARRAY_A );
        }

        if (!empty($productRow)) {
            $result =  $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}atum_product_data SET supplier_sku = null WHERE product_id = %d", $productRow['product_id']));

            if (false === $result) {
                $errorMessage = esc_html__("Error on value unset (id {$productRow['product_id']})");
            }
        }

        return $errorMessage;
    }

    public function codeValueNotUsed($value)
    {
        global $wpdb;

        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}atum_product_data WHERE supplier_sku = %s;", $value));

        return empty($record);
    }

    public function getEmptyCodesProductsQuery($offset = 0, $limit = -1)
    {
        global $wpdb;
        $postIds = $wpdb->get_col("
            SELECT
              p.ID,
              apd.*
            FROM
              {$wpdb->prefix}posts p
              LEFT JOIN `{$wpdb->prefix}atum_product_data` apd
                ON apd.product_id = p.ID
            WHERE apd.`product_id` IS NOT NULL
              AND (
                apd.`supplier_sku` <> ''
                AND apd.`supplier_sku` IS NOT NULL
              )
        ");

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
            'post__not_in' => empty($postIds) ? array(0) : array_values($postIds),
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
        global $wpdb;

        $postIds = $wpdb->get_col("
            SELECT
              p.ID,
              apd.*
            FROM
              {$wpdb->prefix}posts p
              LEFT JOIN `{$wpdb->prefix}atum_product_data` apd
                ON apd.product_id = p.ID
            WHERE apd.`product_id` IS NOT NULL
              AND (
                apd.`supplier_sku` LIKE '%".esc_sql($searchStr)."%'
              )
        ");

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
            'post__in' => empty($postIds) ? array(0) : array_values($postIds),
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

        $postsIds = array_merge($simpleProductsIds, $actualProductsIds);


        if (!empty($postsIds)) {
            $codeValuePostsSql = " OR {$wpdb->posts}.ID IN (".implode(',', $postsIds).") ";

            if (strtoupper(substr(trim($search), 0, 3)) === 'AND') {
                $search = trim($search);
                $search = " AND (".substr($search, 3)." {$codeValuePostsSql}) ";

            } else {
                $search .= $codeValuePostsSql;
            }
        }

        return $search;
    }
}
