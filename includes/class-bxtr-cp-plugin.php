<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        BXTR_CP_Settings::maybe_upgrade_defaults();
        BXTR_CP_Products::init();
        BXTR_CP_Admin::init();
        BXTR_CP_Tutor::init();
        BXTR_CP_Checkout::init();
        BXTR_CP_Shortcodes::init();
    }
}
