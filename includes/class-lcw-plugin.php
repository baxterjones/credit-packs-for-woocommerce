<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        LCW_Products::init();
        LCW_Admin::init();
        LCW_Tutor::init();
        LCW_Checkout::init();
    }
}
