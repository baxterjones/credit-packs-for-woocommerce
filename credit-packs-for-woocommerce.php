<?php
/**
 * Plugin Name: Credit Packs for WooCommerce
 * Description: Sell prepaid credit packs in WooCommerce and let customers redeem credits on selected products.
 * Version: 1.0
 * Author: Baxter Jones
 * Author URI: https://baxtersweb.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: credit-packs-for-woocommerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BXTR_CP_VERSION', '1.0' );
define( 'BXTR_CP_PLUGIN_FILE', __FILE__ );
define( 'BXTR_CP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BXTR_CP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-activator.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-settings.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-products.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-ledger.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-admin.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-tutor.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-checkout.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-shortcodes.php';
require_once BXTR_CP_PLUGIN_DIR . 'includes/class-bxtr-cp-plugin.php';

register_activation_hook( __FILE__, array( 'BXTR_CP_Activator', 'activate' ) );

add_action( 'plugins_loaded', function() {
    BXTR_CP_Plugin::instance();
} );
