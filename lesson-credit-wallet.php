<?php
/**
 * Plugin Name: Lesson Credit Wallet
 * Description: Adds prepaid lesson credits, expiry dates, user balances, and credit ledger tools for WooCommerce/Tutor LMS lesson bookings.
 * Version: 1.0.4
 * Author: Baxtersweb
 * Author URI: https://baxtersweb.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lesson-credit-wallet
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LCW_VERSION', '1.0.4' );
define( 'LCW_PLUGIN_FILE', __FILE__ );
define( 'LCW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LCW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LCW_PLUGIN_DIR . 'includes/class-lcw-activator.php';
require_once LCW_PLUGIN_DIR . 'includes/class-lcw-products.php';
require_once LCW_PLUGIN_DIR . 'includes/class-lcw-ledger.php';
require_once LCW_PLUGIN_DIR . 'includes/class-lcw-admin.php';
require_once LCW_PLUGIN_DIR . 'includes/class-lcw-tutor.php';
require_once LCW_PLUGIN_DIR . 'includes/class-lcw-checkout.php';
require_once LCW_PLUGIN_DIR . 'includes/class-lcw-plugin.php';

register_activation_hook( __FILE__, array( 'LCW_Activator', 'activate' ) );

add_action( 'plugins_loaded', function() {
    LCW_Plugin::instance();
} );
