<?php

namespace UkrSolution\UpcEanGenerator;

require_once ABSPATH . 'wp-admin/includes/upgrade.php'; 

class Database
{
    public static $tableUploads = "uean_generator_files";
    public static $tableCodes = "uean_generator_codes";

    public static function checkTables()
    {
        global $wpdb;

        try {
            $db = $wpdb->dbname;
            $key = "Tables_in_{$db}";

            $plTables = array(
                $wpdb->prefix . self::$tableUploads,
                $wpdb->prefix . self::$tableCodes,
            );

            $result = $wpdb->get_results("SHOW TABLES");
            $tables = array();

            foreach ($result as $value) {
                $tables[] = $value->$key;
            }

            if (count(array_diff($plTables, $tables)) > 0) {
                self::createTables();
            }
        } catch (\Throwable $th) {
        }
    }

    public static function setupTables($network_wide)
    {
        global $wpdb;

        if (is_multisite() && $network_wide) {
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                self::createTables();
                restore_current_blog();
            }
        } else {
            self::createTables();
        }

        self::createTables();
    }

    public static function createTables()
    {
        global $wpdb;

        $tblPaperFormats = $wpdb->prefix . self::$tableUploads;
        $sql = "CREATE TABLE `{$tblPaperFormats}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `file_name` varchar(255) DEFAULT NULL,
            `uploaded_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `file_md5` varchar(32) DEFAULT '',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        dbDelta($sql);

        $tblPaperFormats = $wpdb->prefix . self::$tableCodes;
        $sql = "CREATE TABLE `{$tblPaperFormats}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `code` varchar(14) DEFAULT NULL,
            `type` varchar(13) DEFAULT NULL,
            `is_used` tinyint(1) DEFAULT 0 NOT NULL,
            `file_id` int(10) DEFAULT NULL,
            `integration` varchar(64) DEFAULT NULL,
            `product_id` int(11) DEFAULT NULL,
            `meta_key` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
        dbDelta($sql);
    }
}
