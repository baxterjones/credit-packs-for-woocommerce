<?php
/**
 * Uninstall cleanup for Credit Packs for WooCommerce.
 *
 * Data is only removed when the store owner explicitly enables the
 * "Remove plugin data on uninstall" setting before deleting the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$bxtr_cp_settings = get_option( 'bxtr_cp_settings', array() );
if ( ! is_array( $bxtr_cp_settings ) || ( isset( $bxtr_cp_settings['remove_data_on_uninstall'] ) && 'yes' !== $bxtr_cp_settings['remove_data_on_uninstall'] ) ) {
    return;
}

global $wpdb;

$bxtr_cp_table = $wpdb->prefix . 'bxtr_cp_credit_ledger';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$bxtr_cp_table}" );

$bxtr_cp_options = array(
    'bxtr_cp_settings',
    'bxtr_cp_installed_at',
    'bxtr_cp_version',
);

foreach ( $bxtr_cp_options as $bxtr_cp_option ) {
    delete_option( $bxtr_cp_option );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'bxtr_cp_credit_balance' ), array( '%s' ) );

$bxtr_cp_post_meta_keys = array(
    '_bxtr_cp_credit_type',
    '_bxtr_cp_credits_required',
    '_bxtr_cp_credits_granted',
    '_bxtr_cp_expiry_days',
    '_bxtr_cp_total_credits_granted',
    '_bxtr_cp_total_credits_redeemed',
    '_bxtr_cp_total_credits_returned',
    '_bxtr_cp_granted_credits',
    '_bxtr_cp_redeemed_credits',
    '_bxtr_cp_cancel_reversed',
);

foreach ( $bxtr_cp_post_meta_keys as $bxtr_cp_meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $bxtr_cp_meta_key ), array( '%s' ) );
}

$bxtr_cp_order_item_meta_keys = array(
    '_bxtr_cp_credits_used',
    '_bxtr_cp_credits_granted',
    '_bxtr_cp_credits_returned',
);

$bxtr_cp_order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
foreach ( $bxtr_cp_order_item_meta_keys as $bxtr_cp_meta_key ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $bxtr_cp_order_itemmeta_table, array( 'meta_key' => $bxtr_cp_meta_key ), array( '%s' ) );
}
