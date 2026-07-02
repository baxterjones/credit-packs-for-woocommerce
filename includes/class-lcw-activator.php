<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Activator {
    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'lcw_credit_ledger';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NULL DEFAULT NULL,
            product_id BIGINT UNSIGNED NULL DEFAULT NULL,
            change_amount INT NOT NULL,
            balance_after INT NOT NULL DEFAULT 0,
            entry_type VARCHAR(40) NOT NULL,
            expiry_date DATE NULL DEFAULT NULL,
            note TEXT NULL,
            created_by BIGINT UNSIGNED NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY entry_type (entry_type),
            KEY expiry_date (expiry_date),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );

        if ( ! get_option( 'lcw_installed_at' ) ) {
            update_option( 'lcw_installed_at', current_time( 'mysql' ) );
        }

        update_option( 'lcw_version', LCW_VERSION );
    }
}
