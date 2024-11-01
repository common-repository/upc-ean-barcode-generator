<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class HwpGtin extends DefaultField
{
    const OPTION_VALUE = 'hwp_gtin';
    const OPTION_NAME = 'GTIN - WooCommerce UPC, EAN, and ISBN';
    const POST_META_KEY_PRODUCT = 'hwp_product_gtin';
    const POST_META_KEY_VARIATION = 'hwp_var_gtin';
    const PLUGIN_NAME = 'woo-add-gtin/woocommerce-gtin.php';

    public function getCodeValue($post)
    {
        $postObj = is_a($post, \WP_Post::class) ? $post : get_post((int)$post);
        return 'product_variation' === $postObj->post_type
            ? get_post_meta($postObj->ID, static::POST_META_KEY_VARIATION, true)
            : get_post_meta($postObj->ID, static::POST_META_KEY_PRODUCT, true);
    }

    public function setCodeValue($post, $value, &$usedMetaKey)
    {
        $postObj = is_a($post, \WP_Post::class) ? $post : get_post((int)$post);

        if ('product_variation' === $postObj->post_type) {
            $usedMetaKey = static::POST_META_KEY_VARIATION;
            return false !== update_post_meta($postObj->ID , static::POST_META_KEY_VARIATION, $value);
        } else {
            $usedMetaKey = static::POST_META_KEY_PRODUCT;
            return false !== update_post_meta($postObj->ID , static::POST_META_KEY_PRODUCT, $value);
        }
    }

    public function unsetCodeValue($codeRow)
    {
        $metaKey = null;
        if (!empty($codeRow->meta_key)) {
            $metaKey = $codeRow->meta_key;
            $args = array(
                'meta_key' => $metaKey,
                'meta_value' => $codeRow->code,
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'any',
                'posts_per_page' => -1
            );
        } else {
            $args = array(
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => static::POST_META_KEY_PRODUCT,
                        'value' => $codeRow->code,
                    ),
                    array(
                        'key' => static::POST_META_KEY_VARIATION,
                        'value' => $codeRow->code,
                    ),
                ),
                'post_type' => array('product', 'product_variation'),
                'post_status' => 'any',
                'posts_per_page' => -1
            );
        }

        if (!empty(intval($codeRow->product_id))) {
            $args['post__in'] = array($codeRow->product_id);
        }

        $posts = get_posts($args);

        if (count($posts) > 1) {
            $errorMessage = esc_html__('Given code found in more than one product(id: '.implode(', ', wp_list_pluck($posts, 'ID')).')', 'upc-ean-generator');
        } elseif (empty($posts)) {
            $errorMessage = ''; 
        } else {
            $postId = is_a($posts[0], \WP_Post::class) ? $posts[0]->ID : (int)$posts[0];
            $metaKey = !empty($metaKey)
                ? $metaKey
                : ('product_variation' === $posts[0]->post_type ? static::POST_META_KEY_VARIATION : static::POST_META_KEY_PRODUCT);
            $errorMessage = false === update_post_meta($postId, $metaKey, '')
                ? esc_html__("Unset product data failed (id {$postId})", 'upc-ean-generator')
                : '';
        }

        return $errorMessage;
    }

    public function codeValueNotUsed($value)
    {
        $args = array(
            'post_type' => static::POST_TYPES,
            'post_status' => static::POST_STATUSES,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => static::POST_META_KEY_PRODUCT,
                    'value'   => $value,
                    'compare' => '=',
                ),
                array(
                    'key'     => static::POST_META_KEY_VARIATION,
                    'value'   => $value,
                    'compare' => '=',
                ),
            ),
            'suppress_filters' => true,
            'lang' => 'all',
        );

        $result = new \WP_Query($args);

        return $result->found_posts === 0;
    }

    public function getEmptyCodesProductsQuery($offset = 0, $limit = 0)
    {
        $args = array(
            'post_type' => 'product',
            'post_status' => static::POST_STATUSES,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'simple',
                ),
            ),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => static::POST_META_KEY_PRODUCT,
                    'compare'   => 'NOT EXISTS',
                ),
                array(
                    'key'     => static::POST_META_KEY_PRODUCT,
                    'value'   => '',
                    'compare' => '=',
                ),
            ),
            'suppress_filters' => true,
            'lang' => 'all',
        );

        $simpleProductsIds = (new \WP_Query($args))->posts;

        unset($args['tax_query']);
        $args['post_type'] = 'product_variation';
        $args['fields'] = 'id=>parent';
        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key'     => static::POST_META_KEY_VARIATION,
                'compare'   => 'NOT EXISTS',
            ),
            array(
                'key'     => static::POST_META_KEY_VARIATION,
                'value'   => '',
                'compare' => '=',
            ),
        );
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
            'meta_query' => array(
                array(
                    'key'     => static::POST_META_KEY_PRODUCT,
                    'value'   => esc_sql($searchStr),
                    'compare' => 'LIKE',
                ),
            ),
            'suppress_filters' => true,
            'lang' => 'all',
        );

        $simpleProductsIds = (new \WP_Query($args))->posts;

        unset($args['tax_query']);
        $args['post_type'] = 'product_variation';
        $args['fields'] = 'id=>parent';
        $args['meta_query'] = array(
            array(
                'key'     => static::POST_META_KEY_VARIATION,
                'value'   => esc_sql($searchStr),
                'compare' => 'LIKE',
            ),
        );
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
