<?php

namespace UkrSolution\UpcEanGenerator\features\settings;

class Settings
{
    private $post = array();
    private $dbOptionKey = "uegen-settings-options";

    public function __construct()
    {
        $this->formListener();
    }

    public function formListener()
    {
        try {
            if (isset($_POST) && !empty($_POST)) {
                $keys = array(
                    'tab',
                    'code-type',
                    'code-store-field',
                    'code-store-custom-field',
                    'lk',
                    'new-products-code-source',
                    'generate-code-source',
                );
                foreach ($keys as $key) {
                    if (isset($_POST[$key])) {
                        $this->post[$key] = sanitize_text_field($_POST[$key]);
                    }
                }
            }

            $this->formSubmit();
        } catch (\Throwable $th) {
        }
    }

    public function getField($tab = "", $field = "", $defaultValue = "")
    {
        try {
            $settings = get_option($this->dbOptionKey, array());

            if (!$tab) return $settings;

            if (!isset($settings[$tab])) return $defaultValue;

            if (!$field) return $settings[$tab];

            if (!isset($settings[$tab][$field])) return $defaultValue;

            if (!$settings[$tab][$field] && $defaultValue) return $defaultValue;

            return $settings[$tab][$field];
        } catch (\Throwable $th) {
            return "";
        }
    }

    private function formSubmit()
    {
        try {
            if (!$this->post) {
                return;
            }

            if (!isset($this->post["tab"])) {
                return;
            }

            $settings = get_option($this->dbOptionKey, array());

            $settings[$this->post["tab"]] = $this->post;

            update_option($this->dbOptionKey, $settings);
        } catch (\Throwable $th) {
        }
    }

    public function save()
    {
        $this->formListener();
        wp_send_json_success();
    }

    public function get()
    {
        return get_option($this->dbOptionKey, array());
    }

    public function getCodeStoreFieldOptions()
    {
        $codeStoreFields = array();
        $codeStoreFields = apply_filters('uegen_code_store_field_options', $codeStoreFields);

        return $codeStoreFields;
    }

    public function checkCustomField()
    {
        $post = array();
        foreach (array('custom-field-value', 'postType') as $key) {
            if (isset($_POST[$key])) {
                $post[$key] = sanitize_text_field($_POST[$key]);
            }
        }

        $field = trim($post['custom-field-value']);
        $postTypes = "'" . implode("','", array('product', 'product_variation')) . "'";

        $count = $this->getCustomFieldCount($field, $postTypes);

        wp_send_json_success(array('count' => $count));
    }

    protected function getCustomFieldCount($field, $postTypes)
    {
        global $wpdb;

        $response = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT COUNT(DISTINCT p.`ID`) as 'count'
                FROM `{$wpdb->prefix}postmeta` AS pm, `{$wpdb->prefix}posts` AS p
                WHERE pm.`meta_key` = BINARY %s
                AND pm.`post_id` = p.`ID`
                AND p.`post_type` IN ($postTypes)
                ",
                array($field)
            )
        );

        return $response->count;
    }
}
