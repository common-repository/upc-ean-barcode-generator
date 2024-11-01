<?php

namespace UkrSolution\UpcEanGenerator\features\Integrations;

use UkrSolution\UpcEanGenerator\features\settings\Settings;

class CustomField extends DefaultField
{
    const OPTION_VALUE = 'custom';
    const OPTION_NAME = 'Custom field';
    const SETTINGS_KEY_FOR_CUSTOM_FIELD = 'code-store-custom-field';
    const DEFAULT_POST_META_KEY = '_upc_ean_generator_code';
    const INPUT_NAME = '';

    protected $postMetaKey = '';

    protected function __construct()
    {
        parent::__construct();
        $this->postMetaKey = (new Settings())->getField("general", self::SETTINGS_KEY_FOR_CUSTOM_FIELD, self::DEFAULT_POST_META_KEY);
    }

    public function getCodeValue($post)
    {
        return get_post_meta(is_a($post, \WP_Post::class) ? $post->ID : (int)$post, $this->postMetaKey, true);
    }

    public function getCodeInputName($post)
    {
        return '';
    }

    public function setCodeValue($post, $value, &$usedMetaKey)
    {
        $usedMetaKey = $this->postMetaKey;
        $postId = is_a($post, \WP_Post::class) ? $post->ID : (int)$post;
        $result = $value === get_post_meta($postId, $this->postMetaKey, true)
            || false !== update_post_meta($postId, $this->postMetaKey, $value);
        return $result;
    }
    public function unsetCodeValue($codeRow)
    {
        $metaKey = !empty($codeRow->meta_key) ? $codeRow->meta_key : $this->postMetaKey;
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
                    'key'     => $this->postMetaKey,
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

    public function getEmptyCodesProductsQuery($offset = 0, $limit = -1)
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
                    'key'     => $this->postMetaKey,
                    'compare'   => 'NOT EXISTS',
                ),
                array(
                    'key'     => $this->postMetaKey,
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
                    'key'     => $this->postMetaKey,
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
}
