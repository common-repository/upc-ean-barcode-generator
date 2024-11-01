<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

class DefaultField extends CodeStoreField
{
    const OPTION_VALUE = 'default';
    const OPTION_NAME = 'Barcode';
    const POST_META_KEY = 'usbs_barcode_field';
    const INPUT_NAME = 'usbs_barcode_field';

    public function getCodeValue($post)
    {
        return get_post_meta(is_a($post, \WP_Post::class) ? $post->ID : (int)$post, static::POST_META_KEY, true);
    }

    public function getCodeInputName($post)
    {
        if ('product' === $post->post_type) {
            return 'input[name="usbs_barcode_field"]';
        } elseif('product_variation' === $post->post_type) {
            return '.woocommerce_variation:first-child input[name^="v_usbs_barcode_field"]';
        } else {
            return '';
        }
    }

    public function setCodeValue($post, $value, &$usedMetaKey)
    {
        $usedMetaKey = static::POST_META_KEY;
        return false !== update_post_meta(is_a($post, \WP_Post::class) ? $post->ID : (int)$post, static::POST_META_KEY, $value);

    }

    public function unsetCodeValue($codeRow)
    {
        $metaKey = !empty($codeRow->meta_key) ? $codeRow->meta_key : static::POST_META_KEY;
        $args = array(
            'meta_key' => $metaKey,
            'meta_value' => $codeRow->code,
            'post_type' => array('product', 'product_variation'),
            'post_status' => 'any',
            'posts_per_page' => -1
        );

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
                array(
                    'key'     => static::POST_META_KEY,
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
                'relation' => 'OR',
                array(
                    'key'     => static::POST_META_KEY,
                    'compare'   => 'NOT EXISTS',
                ),
                array(
                    'key'     => static::POST_META_KEY,
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
                    'key'     => static::POST_META_KEY,
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

    public function addSearchQueryTables($query, $searchStr)
    {
        $meta_query = $query->get('meta_query');

        $srchMetaQuery = array(
            'relation' => 'OR',
            array(
                'key' => static::POST_META_KEY,
                'value' => $searchStr,
                'compare' => '!=',
            ),
            array(
                'key' => static::POST_META_KEY,
                'value' => $searchStr,
                'compare' => '=',
            ),

        );

        if (!is_array($meta_query)) {
            $meta_query = array();
            $meta_query[] = $srchMetaQuery;
        } else {
            $meta_query = array(
                'relation' => 'OR',
                $srchMetaQuery,
                $meta_query,
            );
        }

        $query->set('meta_query', $meta_query);

        return $query;
    }
}
