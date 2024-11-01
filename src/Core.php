<?php

namespace UkrSolution\UpcEanGenerator;

use UkrSolution\UpcEanGenerator\features\codes\Codes;
use UkrSolution\UpcEanGenerator\features\DataSources\SpreadSheet;
use UkrSolution\UpcEanGenerator\features\Integrations\AtumBarcode;
use UkrSolution\UpcEanGenerator\features\Integrations\AtumSupplierSku;
use UkrSolution\UpcEanGenerator\features\Integrations\CustomField;
use UkrSolution\UpcEanGenerator\features\Integrations\DefaultField;
use UkrSolution\UpcEanGenerator\features\Integrations\EanForWc;
use UkrSolution\UpcEanGenerator\features\Integrations\HwpGtin;
use UkrSolution\UpcEanGenerator\features\Integrations\SkuWooCommerce;
use UkrSolution\UpcEanGenerator\features\Integrations\TsGtin;
use UkrSolution\UpcEanGenerator\features\Integrations\TsMpn;
use UkrSolution\UpcEanGenerator\features\Integrations\WeposBarcode;
use UkrSolution\UpcEanGenerator\features\Integrations\WpmGtinCode;
use UkrSolution\UpcEanGenerator\features\Integrations\ZettleBarcode;
use UkrSolution\UpcEanGenerator\features\products\Products;
use UkrSolution\UpcEanGenerator\features\settings\Settings;

class Core
{
    protected $products;
    protected $codes;
    protected $settings;
    protected $spreadSheet;

    public function __construct()
    {
        define('UEGEN_PLUGIN_BASE_URL', plugin_dir_url(__FILE__));
        define('UEGEN_PLUGIN_BASE_PATH', plugin_dir_path(__FILE__));

        add_action('admin_menu', array($this, 'createMenu'), 9);
        add_action('admin_menu', array($this, 'adminEnqueueScripts'), 9);

        $this->products = new Products();

        $this->codes = new Codes();
        add_action('wp_ajax_uegen_regenerate_code', array($this->codes, 'regenerateCode'));
        add_action('wp_ajax_uegen_generate_products_codes', array($this->products, 'generateProductsCodes'));
        add_action('wp_ajax_uegen_unassign_imported_file_data', array($this->products, 'unassignFromFile'));

        $this->settings = new Settings();
        add_action('wp_ajax_uegen_save_settings', array($this->settings, 'save'));
        add_action('wp_ajax_uegen_check_custom_field', array($this->settings, 'checkCustomField'));

        $this->spreadSheet = new SpreadSheet();
        add_action('wp_ajax_uegen_upload_spreadsheet_file', array($this->spreadSheet, 'uploadDataFile'));
        add_action('wp_ajax_uegen_import_data_from_file', array($this->spreadSheet, 'importFromFile'));
        add_action('wp_ajax_uegen_get_imported_files_info', array($this->spreadSheet, 'getImportedFilesInfo'));
        add_action('wp_ajax_uegen_delete_imported_file_data', array($this->spreadSheet, 'deleteImportedFileData'));
        add_action('wp_ajax_uegen_get_products_count_info', array($this->products, 'getProductsInfo'));

        DefaultField::register();
        SkuWooCommerce::register(); 
        EanForWc::register(); 
        WpmGtinCode::register(); 
        HwpGtin::register(); 
        WeposBarcode::register(); 
        TsGtin::register(); 
        TsMpn::register(); 
        ZettleBarcode::register(); 
        AtumSupplierSku::register(); 
        AtumBarcode::register(); 
        CustomField::register();
    }

    public function createMenu()
    {
        add_menu_page(
            __('UPC/EAN codes', 'upc-ean-generator'),
            __('UPC/EAN codes', 'upc-ean-generator'),
            'read',
            'upc-ean-generator',
            array($this, 'null'),
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTAgM0gxLjJWMTdIMFYzWk02LjIgM0g3LjRWMTMuNjUxOEg2LjJWM1pNOS40IDNIMTAuNlYxN0g5LjRWM1pNMTIuNiAzSDEzLjZWMTMuNjQ1N0gxMi42VjNaTTE4LjggM0gyMFYxN0gxOC44VjNaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNMTcgM0gxNS44VjEzLjY1SDE3VjNaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNNC4yIDNIMy4yVjEzLjY1SDQuMlYzWiIgZmlsbD0id2hpdGUiLz4KPC9zdmc+Cg=='
        );
        add_submenu_page('upc-ean-generator', __('Settings', 'upc-ean-generator'), __('Settings', 'upc-ean-generator'), 'read', 'upc-ean-generator', array($this, 'pageSettings'));

        add_submenu_page('upc-ean-generator', __('Support', 'upc-ean-generator'), '<span class="upc-ean-generator-support">' . __('Support', 'upc-ean-generator') . '</span>', 'read', 'upc-ean-generator-support', array($this, 'null'));

        add_submenu_page('upc-ean-generator', __('FAQ', 'upc-ean-generator'), '<span class="upc-ean-generator-faq">' . __('FAQ', 'upc-ean-generator') . '</span>', 'read', 'upc-ean-generator-faq', array($this, 'null'));

        add_submenu_page('', __('Barcode Scanner', 'upc-ean-generator'), __('Barcode Scanner', 'upc-ean-generator'), 'read', 'bs-mobile-home', array($this, 'mobilePageHome'));
    }

    public function adminEnqueueScripts()
    {
        global $wp_version;

        wp_enqueue_script("uegenerator_loader", plugin_dir_url(__FILE__)."../assets/js/index-2.0.1-basic-1721201707131.js", array("jquery"), 1721201707131, true);

    wp_enqueue_style('uegenerator_style', plugin_dir_url(__FILE__)."../assets/css/index-2.0.1-basic-1721201707131.css", false, 1721201707131);

    $appJsPath = "";

    $vendorJsPath = "";


        $jsL10n = require_once UEGEN_PLUGIN_BASE_PATH . 'config/jsL10n.php';

        wp_localize_script("uegenerator_loader", "uegen", array(
            'appJsPath' => $appJsPath,
            'vendorJsPath' => $vendorJsPath,
            'websiteUrl' => get_bloginfo("url"),
            'adminUrl' => get_admin_url(),
            'pluginUrl' => plugin_dir_url(__DIR__ ),
            'ajaxUrl' => get_admin_url() . 'admin-ajax.php',
            'pluginVersion' => '2.0.1',
            'nonce' => wp_create_nonce('wp_rest'),
            'settings' => $this->settings->get(),
            'codeStoreFieldOptions' => $this->settings->getCodeStoreFieldOptions(),
            'importedFilesCount' => count($this->spreadSheet->getImportedFilesInfo(true)),
            'lk' => $this->settings->getField('general', 'lk'),
            'uid' => get_current_user_id(),
            'wp_version' => $wp_version,
        ));

        wp_localize_script("uegenerator_loader", "uegenL10n", $jsL10n);
    }

    public function pageSettings () {
        $settings = $this->settings;
        require_once UEGEN_PLUGIN_BASE_PATH . "features/settings/index.php";
    }

    public function null () {}
}
