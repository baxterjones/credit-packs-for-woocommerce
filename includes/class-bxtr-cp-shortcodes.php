<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Shortcodes {
    public static function init() {
        add_shortcode( 'bxtr_credit_packs', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts = array() ) {
        $atts = shortcode_atts( array(
            'type'       => 'card',
            'product_id' => 0,
        ), $atts, 'bxtr_credit_packs' );

        $type = sanitize_key( $atts['type'] );

        if ( 'balance' === $type ) {
            return self::render_balance();
        }

        if ( 'product' === $type ) {
            return self::render_product( absint( $atts['product_id'] ) );
        }

        return self::render_dashboard_card();
    }

    private static function render_balance() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $balance = BXTR_CP_Ledger::get_balance( get_current_user_id() );
        $data = array(
            'credit_balance'      => $balance,
            'credit_label'        => BXTR_CP_Settings::credit_label( $balance ),
            'credit_label_plural' => BXTR_CP_Settings::get( 'credit_label_plural' ),
        );

        ob_start();
        ?>
        <div class="bxtr-cp-dashboard-card bxtr-cp-dashboard-card--shortcode bxtr-cp-dashboard-card--balance" style="<?php echo esc_attr( BXTR_CP_Settings::frontend_style_attr() ); ?>">
            <div class="bxtr-cp-dashboard-card__main">
                <div class="bxtr-cp-dashboard-card__label"><span><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'dashboard_title' ), $data ) ); ?></span><?php echo BXTR_CP_Settings::safe_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <div class="bxtr-cp-dashboard-card__balance-row">
                    <span class="bxtr-cp-dashboard-card__balance-text"><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'dashboard_balance' ), $data ) ); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_dashboard_card() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        ob_start();
        BXTR_CP_Tutor::dashboard_summary_block( false, 'bxtr-cp-dashboard-card-shortcode' );
        return ob_get_clean();
    }

    private static function render_product( $product_id ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '';
        }

        if ( ! $product_id ) {
            $bxtr_cp_product = wc_get_product( get_the_ID() );
            if ( $bxtr_cp_product && is_a( $bxtr_cp_product, 'WC_Product' ) ) {
                $product_id = $bxtr_cp_product->get_id();
            }
        }

        if ( ! $product_id ) {
            return '';
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return '';
        }

        $required = BXTR_CP_Products::get_credits_required( $product_id );
        $granted  = BXTR_CP_Products::get_credits_granted( $product_id );

        ob_start();
        if ( $required > 0 ) {
            BXTR_CP_Checkout::redeemable_product_credit_box( $product, $required );
        } elseif ( $granted > 0 ) {
            BXTR_CP_Checkout::pack_credit_box( $product, $granted );
        }
        return ob_get_clean();
    }
}
