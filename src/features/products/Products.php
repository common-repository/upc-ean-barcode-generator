<?php

namespace UkrSolution\UpcEanGenerator\features\products;

use UkrSolution\UpcEanGenerator\features\codes\Codes;
use UkrSolution\UpcEanGenerator\features\DataSources\SpreadSheet;
use UkrSolution\UpcEanGenerator\features\settings\Settings;
use UkrSolution\UpcEanGenerator\Hooks;
use function EasyWPSMTP\Vendor\GuzzleHttp\Psr7\str;

class Products
{
    const DATA_GENERATE = 'generate';
    const DATA_DATABASE = 'database';
    const DATA_DONOTHING = 'donothing';
    public $fieldName = "";
    protected $upcEanFieldName = "usbs_barcode_field";
    protected $fieldLabel = "";
    protected $postTypes = array('product', 'product_variation');
    protected $postStatuses = array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash');
    protected $settings;
    protected $spreadSheet;
    protected $newProductsCodeSource = self::DATA_GENERATE;
    protected $codeStoreFieldOption;
    protected $newCustomFieldDatabaseCodeRowId = '';
    protected $newCustomFieldDatabaseCodeValue = '';
    protected $productFirstTimePublished = false;
    protected $showBarcodeField = true;

    function __construct()
    {
        $this->settings = new Settings();
        $this->spreadSheet = new SpreadSheet();

        $this->codeStoreFieldOption = $this->settings->getField("general", "code-store-field", $this->upcEanFieldName);
        $this->fieldName = $this->upcEanFieldName;
        $this->fieldLabel = __("Barcode", 'upc-ean-generator');
        $this->newProductsCodeSource = $this->settings->getField("general", "new-products-code-source", self::DATA_GENERATE);

        $activePlugins = is_multisite() ? get_site_option('active_sitewide_plugins') : get_option('active_plugins');
        foreach ($activePlugins as $activePlugin) {
            if (preg_match('/barcode-scanner-(basic|business|premium|mobile)\/barcode-scanner.php/', $activePlugin)) {
                $this->showBarcodeField = false;
                break;
            }
        }

        add_action('woocommerce_process_product_meta', array($this, 'woocommerce_process_product_meta'));
        add_action('woocommerce_product_options_sku', array($this, 'woocommerce_product_options_sku'));

        add_action('woocommerce_variation_options', array($this, 'woocommerce_variation_options'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'woocommerce_save_product_variation'), 10, 2);

        add_action('save_post', array($this, 'addCodeValueForNewProduct'), 99999, 3);

        add_action('edit_form_top', array($this, 'noFreeCodeFoundInDatabaseNotice'));

        add_action('posts_search', array($this, 'searchByProductCodeFieldParams'), PHP_INT_MAX, 2);

    }

    public function searchByProductCodeFieldParams($search, $query)
    {
        global $pagenow;



        if (
            is_admin()
            && $query->is_main_query()
            && $pagenow === 'edit.php'
            && function_exists('is_woocommerce') && is_woocommerce()
            && isset($_REQUEST['s']) && !empty($_REQUEST['s'])
        ) {
            $searchStr = $_REQUEST['s'];
        } elseif (
            !is_admin()
            && $query->is_search()
            && $query->is_main_query()
            && !empty($query->query_vars['s'])
        ) {
            $searchStr = $query->query_vars['s'];
        } else {
            $searchStr = '';
        }

        if (!empty($searchStr)) {

            $search = $this->addSearchQueryParams($query, $search, $searchStr);
        }

        return $search;
    }

    public function searchByProductCodeFieldTables($query)
    {
        global $pagenow;

        if (
            is_admin()
            && $query->is_main_query()
            && $pagenow === 'edit.php'
            && function_exists('is_woocommerce') && is_woocommerce()
            && isset($_REQUEST['s']) && !empty($_REQUEST['s'])
        ) {
            $searchStr = $_REQUEST['s'];
        } elseif (
            !is_admin()
            && $query->is_search()
            && $query->is_main_query()
            && !empty($query->query_vars['s'])
        ) {
            $searchStr = $query->query_vars['s'];
        } else {
            $searchStr = '';
        }

        if (!empty($searchStr)) {
            $query = $this->addSearchQueryTables($query, $searchStr);
        }

        return $query;
    }


    public function addCodeValueForNewProduct($postId, $post, $update)
    {
        if ($post->post_type !== 'product') {
            return;
        }

        if(
            (defined('DOING_AJAX') && DOING_AJAX)
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        ) {
            return;
        }

        if ( (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || is_int( wp_is_post_revision( $post ) ) || is_int( wp_is_post_autosave( $post ) ) ) {
            return;
        }

        if (empty($_POST['post_ID']) || absint($_POST['post_ID']) !== $postId) {
            return;
        }

        $product = wc_get_product($postId);

        $productIds = $product->is_type('variable') ? $product->get_children() : array($postId);

        foreach ($productIds as $productId) {
            $value = $this->getProductCodeValue($productId);

            if ('' !== $value && null !== $value) {
                continue;
            }

            if (self::DATA_DATABASE === $this->newProductsCodeSource) {
                $codeRow = $this->spreadSheet->getFreeCode($this->settings->getField("general", "code-type", "upc"));
                if (!empty($codeRow)) {
                    $usedMetaKey = '';
                    if ($this->setProductCodeValue($productId, esc_attr($codeRow->code), $usedMetaKey)) {
                        $this->spreadSheet->setCodeIsUsedFlag($codeRow->id);
                        $this->spreadSheet->updateCodeRow($codeRow->id, array(
                            'integration' => $this->settings->getField("general", "code-store-field", 'default'),
                            'product_id' => $productId,
                            'meta_key' => $usedMetaKey,
                        ));
                    }
                }
            } elseif(self::DATA_GENERATE === $this->newProductsCodeSource) {
                for ($i = 0; $i < 100; $i++) {
                    $value = (new Codes())->generate($this->fieldName);

                    if ($this->codeValueNotUsed($productId, $value)) {
                        break;
                    } else {
                        $value = '';
                    }
                }

                $this->setProductCodeValue($productId, esc_attr($value));
            }
        }
    }

    public function woocommerce_product_options_sku()
    {
        global $post;

        if ($this->showBarcodeField) {
            $value = get_post_meta($post->ID, $this->upcEanFieldName, true);
            include(__DIR__ . "/views/field.php");
        }
    }

    public function woocommerce_process_product_meta($postId)
    {
        if (isset($_POST[$this->upcEanFieldName]) && !isset($_POST["v_{$this->upcEanFieldName}"])) {
            $value = sanitize_text_field($_POST[$this->upcEanFieldName]);
            update_post_meta($postId, $this->upcEanFieldName, $value);
        }

        if (isset($_POST["v_{$this->fieldName}"]) && is_array($_POST["v_{$this->fieldName}"])) {
            foreach ($_POST["v_{$this->fieldName}"] as $variationId => $fieldValue) {
                $value = sanitize_text_field($fieldValue);
                update_post_meta($variationId, $this->upcEanFieldName, $value);
            }
        }
    }

    public function woocommerce_variation_options($loop, $variation_data, $variation)
    {
        if ($this->showBarcodeField) {
            $value = get_post_meta($variation->ID, $this->upcEanFieldName, true);;
            include(__DIR__ . "/views/field-variation.php");
        }
    }

    public function woocommerce_save_product_variation($variationId)
    {
        if (isset($_POST["v_{$this->fieldName}"])) {
            $value = sanitize_text_field($_POST["v_{$this->fieldName}"][$variationId]);
            update_post_meta($variationId, $this->upcEanFieldName, $value);
        }
    }

    public function noFreeCodeFoundInDatabaseNotice($post)
    {
        if (
            'product' === $post->post_type
            && 'auto-draft' === $post->post_status
            && self::DATA_DATABASE === $this->newProductsCodeSource
        ) {
            $codeRow = $this->spreadSheet->getFreeCode($this->settings->getField("general", "code-type", "upc"));
            if (empty($codeRow)) {
                $class = 'notice notice-warning is-dismissible';
                $message = __( "Warning: There are no free {$this->fieldLabel} codes left in uploaded files. New products will be created without codes.", "upc-ean-generator");

                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
            }
        }
    }


    public function generateProductsCodes()
    {
        $offset = 0;
        $limit = 50;
        $codeSource = self::DATA_GENERATE;
        $updatedCount = 0;
        $errorMessage = '';

        if (isset($_POST["assignProducts"])) {
            $productsFilterName = sanitize_text_field($_POST["assignProducts"]);
        } else {
            wp_send_json_error(__('Filter type not provided', "upc-ean-generator"));
            wp_die();
        }

        if (isset($_POST["offset"])) {
            $offset = ProductsFilters::ALL === $productsFilterName
                ? (int)sanitize_text_field($_POST["offset"])
                : 0;
        } else {
            wp_send_json_error(__('No offset provided', "upc-ean-generator"));
            wp_die();
        }

        if (isset($_POST["codeSource"])) {
            $codeSource = sanitize_text_field($_POST["codeSource"]);
        } else {
            wp_send_json_error(__('Codes source not provided', "upc-ean-generator"));
            wp_die();
        }

        switch ($productsFilterName) {
            case (ProductsFilters::ALL):
                $posts = $this->getAllProductsQuery($offset, $limit)->posts;
                break;
            default:
                $posts = $this->getProductsWithEmptyCodes($offset, $limit);
        }

        foreach ($posts as $post) {
            if (self::DATA_GENERATE === $codeSource) {
                $codes = new Codes();
                $value = $codes->generate($this->fieldName);
                $this->setProductCodeValue($post->ID, esc_attr($value));

                $updatedCount++;
            } elseif (self::DATA_DATABASE === $codeSource) {
                $codeType = $this->settings->getField("general", "code-type", "upc");
                $codeRow = $this->spreadSheet->getFreeCode($codeType);

                if (empty($codeRow)) {
                    $errorMessage = __('Not enough codes found in database.', "upc-ean-generator");
                    break;
                }

                $previousCodeValue = $this->getProductCodeValue($post);

                $usedMetaKey = '';
                $updatePostMetaResult = $this->setProductCodeValue($post->ID, esc_attr($codeRow->code), $usedMetaKey);

                if (!empty($updatePostMetaResult)) {
                    $updateFlagResult = $this->spreadSheet->setCodeIsUsedFlag($codeRow->id);
                    $this->spreadSheet->updateCodeRow($codeRow->id, array(
                        'integration' => $this->settings->getField("general", "code-store-field", 'default'),
                        'product_id' => $post->ID,
                        'meta_key' => $usedMetaKey,
                    ));

                    if (empty($updateFlagResult)) {
                        $this->setProductCodeValue($post->ID, esc_attr($previousCodeValue));
                    } else {
                        $updatedCount++;
                    }
                } else {
                    $errorMessage = __("Can't update code value.", "upc-ean-generator");
                    break;
                }

            } elseif (self::DATA_DONOTHING === $codeSource) {
                $updatedCount = count($posts);
            } else {
                wp_send_json_error(__('No valid codes source provided', "upc-ean-generator"));
            }
        }

        wp_send_json_success(array(
            "previous_offset" => $offset,
            "offset" => $offset ? $offset + $limit : $limit,
            "limit" => $limit,
            "total" => $this->getAllProductsQuery($offset, $limit)->found_posts,
            "found" => count($posts),
            "updated" => $updatedCount,
            "error_message" => $errorMessage,
        ));
    }

    public function getAllProductsQuery($offset = 0, $limit = 0)
    {

        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_get_all_products_query', new \WP_Query(), $integrationOptionValue, $offset, $limit);
    }

    public function getTotal()
    {

        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_get_all_products_query', new \WP_Query(), $integrationOptionValue, 0, 0)->found_posts;
    }

    public function getProductsWithEmptyCodes($offset = 0, $limit = 0)
    {
        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_get_empty_codes_products_query', new \WP_Query(), $integrationOptionValue, $offset, $limit)->posts;
    }

    public function getEmptyCodesTotal()
    {


        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_get_empty_codes_products_query', new \WP_Query(), $integrationOptionValue, 0, 0)->found_posts;
    }

    public function getProductsInfo()
    {
        wp_send_json_success(array(
            'totalProducts' => $this->getTotal(),
            'totalEmptyCodeProducts' => $this->getEmptyCodesTotal(),
        ));
    }

    protected function getProductCodeValue($productPost)
    {
        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_get_product_code_field_value', '', $integrationOptionValue, $productPost);
    }

    protected function setProductCodeValue($productPost, $value, &$usedMetaKey = '')
    {
        $result = false;
        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        do_action_ref_array('uegen_set_product_code_field_value', array($integrationOptionValue, $productPost, $value, &$result, &$usedMetaKey));

        return false !== $result;
    }

    public function unassignFromFile()
    {
        $fileId = sanitize_text_field($_POST['fileId']);
        $errorMessages = array();

        $assignedCodesFromFileObjectList = $this->spreadSheet->getAssignedCodesFromFile($fileId);

        if (empty($assignedCodesFromFileObjectList)) {
            wp_send_json_error(__('Assigned codes not found in this file.', "upc-ean-generator"));
        }

        foreach ($assignedCodesFromFileObjectList as $codeRow) {
            $errorMessage = '';
            $integrationOptionValue = !empty($codeRow->integration)
                ? $codeRow->integration
                : $this->settings->getField("general", "code-store-field", 'default');

            do_action_ref_array('uegen_unset_product_code_field_value', array($integrationOptionValue, $codeRow, &$errorMessage));

            if (empty($errorMessage)) {
                $this->spreadSheet->setCodeIsUsedFlag($codeRow->id, 0);
                $this->spreadSheet->updateCodeRow($codeRow->id, array(
                    'integration' => '',
                    'product_id' => '',
                    'meta_key' => '',
                ));
            } else {
                $errorMessages[$codeRow->code] = $errorMessage;
            }
        }

        if (empty($errorMessages)) {
            wp_send_json_success();
        } else {
            $allErrorsText = '';
            foreach ($errorMessages as $codeValue => $errorMessage) {
                $allErrorsText .= esc_html($codeValue . ": " . $errorMessage) . "<br>";
            }
            wp_send_json_error($allErrorsText);
        }
    }

    protected function codeValueNotUsed($productPost, $value)
    {
        $result = true;
        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        do_action_ref_array('uegen_check_if_code_value_not_used', array($integrationOptionValue, $productPost, $value, &$result));

        return $result;
    }

    protected function addSearchQueryParams($query, $search, $searchStr)
    {
        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_add_search_query_params', $search, $query, $searchStr, $integrationOptionValue);
    }

    protected function addSearchQueryTables($query, $searchStr)
    {
        $integrationOptionValue = $this->settings->getField("general", "code-store-field", 'default');
        return apply_filters('uegen_add_search_query_tables', $query, $searchStr, $integrationOptionValue);
    }

}
