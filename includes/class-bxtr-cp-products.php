<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Products {
    public static function init() {
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'add_product_panel' ) );
        add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_fields' ) );
    }

    public static function add_product_tab( $tabs ) {
        $tabs['bxtr_cp_product_credits'] = array(
            'label'    => __( 'Credits', 'credit-packs-for-woocommerce' ),
            'target'   => 'bxtr_cp_product_credits_product_data',
            'class'    => array(),
            'priority' => 65,
        );

        return $tabs;
    }

    public static function add_product_panel() {
        global $post;

        $product_id = $post ? $post->ID : 0;
        $credit_type = $product_id ? self::get_credit_type( $product_id ) : 'standard';

        echo '<div id="bxtr_cp_product_credits_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div class="options_group">';

        echo '<p class="form-field"><strong>' . esc_html__( 'Credit Packs for WooCommerce', 'credit-packs-for-woocommerce' ) . '</strong><br>';
        echo '<span class="description">' . esc_html__( 'Choose what this product does in the credit system. A product can either grant credits or require credits, not both.', 'credit-packs-for-woocommerce' ) . '</span></p>';

        woocommerce_wp_select( array(
            'id'          => '_bxtr_cp_credit_type',
            'label'       => __( 'Credit Type', 'credit-packs-for-woocommerce' ),
            'value'       => $credit_type,
            'description' => __( 'Standard products are ignored by the wallet. Credit packs grant credits. Redeemable products use credits.', 'credit-packs-for-woocommerce' ),
            'desc_tip'    => true,
            'options'     => array(
                'standard' => __( 'Standard Product', 'credit-packs-for-woocommerce' ),
                'pack'     => __( 'Credit Pack', 'credit-packs-for-woocommerce' ),
                'product'   => __( 'Redeemable Product', 'credit-packs-for-woocommerce' ),
            ),
        ) );

        echo '<div class="bxtr-cp-product-field bxtr-cp-product-field--product">';
        woocommerce_wp_text_input( array(
            'id'                => '_bxtr_cp_credits_required',
            'label'             => __( 'Credits Required', 'credit-packs-for-woocommerce' ),
            'description'       => __( 'Credits needed to book/redeem this product. Example: normal product = 1, crunch session = 2.', 'credit-packs-for-woocommerce' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            'desc_tip'          => true,
        ) );
        echo '</div>';

        echo '<div class="bxtr-cp-product-field bxtr-cp-product-field--pack">';
        woocommerce_wp_text_input( array(
            'id'                => '_bxtr_cp_credits_granted',
            'label'             => __( 'Credits Granted', 'credit-packs-for-woocommerce' ),
            'description'       => __( 'Credits added when this pack is purchased. Example: 4 Credit Pack = 4.', 'credit-packs-for-woocommerce' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            'desc_tip'          => true,
        ) );

        woocommerce_wp_text_input( array(
            'id'                => '_bxtr_cp_expiry_days',
            'label'             => __( 'Credit Expiry Days', 'credit-packs-for-woocommerce' ),
            'description'       => __( 'How many days granted credits stay valid. Example: 365. Use 0 for no expiry.', 'credit-packs-for-woocommerce' ),
            'type'              => 'number',
            'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
            'desc_tip'          => true,
        ) );
        echo '</div>';

        echo '<p class="form-field bxtr-cp-product-field bxtr-cp-product-field--standard"><span class="description">' . esc_html__( 'This product will not grant or use credits.', 'credit-packs-for-woocommerce' ) . '</span></p>';

        echo '<script>
            jQuery(function($){
                function bxtr_cpToggleProductFields(){
                    var type = $("#_bxtr_cp_credit_type").val();
                    $(".bxtr-cp-product-field").hide();
                    if(type === "pack"){
                        $(".bxtr-cp-product-field--pack").show();
                    } else if(type === "product"){
                        $(".bxtr-cp-product-field--product").show();
                    } else {
                        $(".bxtr-cp-product-field--standard").show();
                    }
                }
                $(document).on("change", "#_bxtr_cp_credit_type", bxtr_cpToggleProductFields);
                bxtr_cpToggleProductFields();
            });
        </script>';

        echo '</div>';
        echo '</div>';
    }

    public static function save_product_fields( $product ) {
        if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
            return;
        }

        $type = isset( $_POST['_bxtr_cp_credit_type'] ) ? sanitize_key( wp_unslash( $_POST['_bxtr_cp_credit_type'] ) ) : 'standard';
        if ( ! in_array( $type, array( 'standard', 'pack', 'product' ), true ) ) {
            $type = 'standard';
        }

        $required = isset( $_POST['_bxtr_cp_credits_required'] ) ? absint( wp_unslash( $_POST['_bxtr_cp_credits_required'] ) ) : 0;
        $granted  = isset( $_POST['_bxtr_cp_credits_granted'] ) ? absint( wp_unslash( $_POST['_bxtr_cp_credits_granted'] ) ) : 0;
        $expiry   = isset( $_POST['_bxtr_cp_expiry_days'] ) ? absint( wp_unslash( $_POST['_bxtr_cp_expiry_days'] ) ) : 0;

        if ( 'product' === $type ) {
            $granted = 0;
            $expiry  = 0;
        } elseif ( 'pack' === $type ) {
            $required = 0;
        } else {
            $required = 0;
            $granted  = 0;
            $expiry   = 0;
        }

        $product->update_meta_data( '_bxtr_cp_credit_type', $type );
        $product->update_meta_data( '_bxtr_cp_credits_required', $required );
        $product->update_meta_data( '_bxtr_cp_credits_granted', $granted );
        $product->update_meta_data( '_bxtr_cp_expiry_days', $expiry );
    }

    public static function get_credit_type( $product_id ) {
        $type = get_post_meta( $product_id, '_bxtr_cp_credit_type', true );
        if ( in_array( $type, array( 'standard', 'pack', 'product' ), true ) ) {
            return $type;
        }

        $required = self::get_credits_required( $product_id );
        $granted  = self::get_credits_granted( $product_id );

        if ( $granted > 0 && $required <= 0 ) return 'pack';
        if ( $required > 0 && $granted <= 0 ) return 'product';
        return 'standard';
    }

    public static function get_credits_required( $product_id ) {
        if ( 'product' !== self::get_credit_type_raw_safe( $product_id ) ) {
            $type = get_post_meta( $product_id, '_bxtr_cp_credit_type', true );
            if ( $type && 'product' !== $type ) return 0;
        }
        return max( 0, (int) get_post_meta( $product_id, '_bxtr_cp_credits_required', true ) );
    }

    public static function get_credits_granted( $product_id ) {
        if ( 'pack' !== self::get_credit_type_raw_safe( $product_id ) ) {
            $type = get_post_meta( $product_id, '_bxtr_cp_credit_type', true );
            if ( $type && 'pack' !== $type ) return 0;
        }
        return max( 0, (int) get_post_meta( $product_id, '_bxtr_cp_credits_granted', true ) );
    }

    private static function get_credit_type_raw_safe( $product_id ) {
        $type = get_post_meta( $product_id, '_bxtr_cp_credit_type', true );
        return in_array( $type, array( 'standard', 'pack', 'product' ), true ) ? $type : '';
    }

    public static function get_expiry_days( $product_id ) {
        if ( 'pack' !== self::get_credit_type( $product_id ) ) return 0;
        return max( 0, (int) get_post_meta( $product_id, '_bxtr_cp_expiry_days', true ) );
    }
}
