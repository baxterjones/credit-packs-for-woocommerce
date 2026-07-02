<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Products {
    public static function init() {
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'add_product_panel' ) );
        add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_fields' ) );
    }

    public static function add_product_tab( $tabs ) {
        $tabs['lcw_lesson_credits'] = array(
            'label'    => __( 'Lesson Credits', 'lesson-credit-wallet' ),
            'target'   => 'lcw_lesson_credits_product_data',
            'class'    => array(),
            'priority' => 65,
        );

        return $tabs;
    }

    public static function add_product_panel() {
        global $post;

        $product_id = $post ? $post->ID : 0;
        $credit_type = $product_id ? self::get_credit_type( $product_id ) : 'standard';

        echo '<div id="lcw_lesson_credits_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';

        echo '<p class="form-field"><strong>' . esc_html__( 'Lesson Credit Wallet', 'lesson-credit-wallet' ) . '</strong><br>';
        echo '<span class="description">' . esc_html__( 'Choose what this product does in the credit system. A product can either grant credits or require credits, not both.', 'lesson-credit-wallet' ) . '</span></p>';

        woocommerce_wp_select( array(
            'id'          => '_lcw_credit_type',
            'label'       => __( 'Credit Type', 'lesson-credit-wallet' ),
            'value'       => $credit_type,
            'description' => __( 'Standard products are ignored by the wallet. Credit packs grant credits. Redeemable lessons use credits.', 'lesson-credit-wallet' ),
            'desc_tip'    => true,
            'options'     => array(
                'standard' => __( 'Standard Product', 'lesson-credit-wallet' ),
                'pack'     => __( 'Credit Pack', 'lesson-credit-wallet' ),
                'lesson'   => __( 'Redeemable Lesson', 'lesson-credit-wallet' ),
            ),
        ) );

        echo '<div class="lcw-product-field lcw-product-field--lesson">';
        woocommerce_wp_text_input( array(
            'id'                => '_lcw_credits_required',
            'label'             => __( 'Credits Required', 'lesson-credit-wallet' ),
            'description'       => __( 'Credits needed to book/redeem this lesson. Example: normal lesson = 1, crunch session = 2.', 'lesson-credit-wallet' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            'desc_tip'          => true,
        ) );
        echo '</div>';

        echo '<div class="lcw-product-field lcw-product-field--pack">';
        woocommerce_wp_text_input( array(
            'id'                => '_lcw_credits_granted',
            'label'             => __( 'Credits Granted', 'lesson-credit-wallet' ),
            'description'       => __( 'Credits added when this pack is purchased. Example: 4 Lesson Pack = 4.', 'lesson-credit-wallet' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            'desc_tip'          => true,
        ) );

        woocommerce_wp_text_input( array(
            'id'                => '_lcw_expiry_days',
            'label'             => __( 'Credit Expiry Days', 'lesson-credit-wallet' ),
            'description'       => __( 'How many days granted credits stay valid. Example: 365. Use 0 for no expiry.', 'lesson-credit-wallet' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            'desc_tip'          => true,
        ) );
        echo '</div>';

        echo '<p class="form-field lcw-product-field lcw-product-field--standard"><span class="description">' . esc_html__( 'This product will not grant or use lesson credits.', 'lesson-credit-wallet' ) . '</span></p>';

        echo '<script>
            jQuery(function($){
                function lcwToggleProductFields(){
                    var type = $("#_lcw_credit_type").val();
                    $(".lcw-product-field").hide();
                    if(type === "pack"){
                        $(".lcw-product-field--pack").show();
                    } else if(type === "lesson"){
                        $(".lcw-product-field--lesson").show();
                    } else {
                        $(".lcw-product-field--standard").show();
                    }
                }
                $(document).on("change", "#_lcw_credit_type", lcwToggleProductFields);
                lcwToggleProductFields();
            });
        </script>';

        echo '</div>';
        echo '</div>';
    }

    public static function save_product_fields( $product ) {
        $type = isset( $_POST['_lcw_credit_type'] ) ? sanitize_key( wp_unslash( $_POST['_lcw_credit_type'] ) ) : 'standard';
        if ( ! in_array( $type, array( 'standard', 'pack', 'lesson' ), true ) ) {
            $type = 'standard';
        }

        $required = isset( $_POST['_lcw_credits_required'] ) ? absint( wp_unslash( $_POST['_lcw_credits_required'] ) ) : 0;
        $granted  = isset( $_POST['_lcw_credits_granted'] ) ? absint( wp_unslash( $_POST['_lcw_credits_granted'] ) ) : 0;
        $expiry   = isset( $_POST['_lcw_expiry_days'] ) ? absint( wp_unslash( $_POST['_lcw_expiry_days'] ) ) : 0;

        if ( 'lesson' === $type ) {
            $granted = 0;
            $expiry  = 0;
        } elseif ( 'pack' === $type ) {
            $required = 0;
        } else {
            $required = 0;
            $granted  = 0;
            $expiry   = 0;
        }

        $product->update_meta_data( '_lcw_credit_type', $type );
        $product->update_meta_data( '_lcw_credits_required', $required );
        $product->update_meta_data( '_lcw_credits_granted', $granted );
        $product->update_meta_data( '_lcw_expiry_days', $expiry );
    }

    public static function get_credit_type( $product_id ) {
        $type = get_post_meta( $product_id, '_lcw_credit_type', true );
        if ( in_array( $type, array( 'standard', 'pack', 'lesson' ), true ) ) {
            return $type;
        }

        $required = self::get_credits_required( $product_id );
        $granted  = self::get_credits_granted( $product_id );

        if ( $granted > 0 && $required <= 0 ) return 'pack';
        if ( $required > 0 && $granted <= 0 ) return 'lesson';
        return 'standard';
    }

    public static function get_credits_required( $product_id ) {
        if ( 'lesson' !== self::get_credit_type_raw_safe( $product_id ) ) {
            $type = get_post_meta( $product_id, '_lcw_credit_type', true );
            if ( $type && 'lesson' !== $type ) return 0;
        }
        return max( 0, (int) get_post_meta( $product_id, '_lcw_credits_required', true ) );
    }

    public static function get_credits_granted( $product_id ) {
        if ( 'pack' !== self::get_credit_type_raw_safe( $product_id ) ) {
            $type = get_post_meta( $product_id, '_lcw_credit_type', true );
            if ( $type && 'pack' !== $type ) return 0;
        }
        return max( 0, (int) get_post_meta( $product_id, '_lcw_credits_granted', true ) );
    }

    private static function get_credit_type_raw_safe( $product_id ) {
        $type = get_post_meta( $product_id, '_lcw_credit_type', true );
        return in_array( $type, array( 'standard', 'pack', 'lesson' ), true ) ? $type : '';
    }

    public static function get_expiry_days( $product_id ) {
        if ( 'pack' !== self::get_credit_type( $product_id ) ) return 0;
        return max( 0, (int) get_post_meta( $product_id, '_lcw_expiry_days', true ) );
    }
}
