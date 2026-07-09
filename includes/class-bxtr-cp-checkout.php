<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Checkout {
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

        add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'display_product_credit_box' ), 5 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'show_cart_item_data' ), 10, 2 );
        add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_internal_order_item_meta' ) );
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_credit_pricing' ), 20 );
        add_action( 'woocommerce_cart_totals_before_order_total', array( __CLASS__, 'cart_totals_credit_note' ) );
        add_action( 'woocommerce_review_order_before_order_total', array( __CLASS__, 'cart_totals_credit_note' ) );
        add_action( 'woocommerce_before_cart_table', array( __CLASS__, 'cart_credit_notice' ) );
        add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'cart_credit_notice' ), 8 );

        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'create_order_line_item_meta' ), 10, 4 );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_paid_order' ) );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_paid_order' ) );
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_cancelled_order' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_cancelled_order' ) );
        add_action( 'woocommerce_order_refunded', array( __CLASS__, 'handle_order_refund_created' ), 10, 2 );
        add_action( 'admin_footer', array( __CLASS__, 'admin_refund_credit_guidance' ) );
    }

    public static function assets() {
        wp_enqueue_style( 'bxtr-cp-frontend', BXTR_CP_PLUGIN_URL . 'assets/css/frontend.css', array(), BXTR_CP_VERSION );
    }

    public static function display_product_credit_box() {
        if ( ! is_user_logged_in() ) return;
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

        $bxtr_cp_product = wc_get_product( get_the_ID() );
        if ( ! $bxtr_cp_product || ! is_a( $bxtr_cp_product, 'WC_Product' ) ) return;

        $product_id = $bxtr_cp_product->get_id();
        $required   = BXTR_CP_Products::get_credits_required( $product_id );
        $granted    = BXTR_CP_Products::get_credits_granted( $product_id );

        if ( $required > 0 ) {
            self::redeemable_product_credit_box( $bxtr_cp_product, $required );
            return;
        }

        if ( $granted > 0 ) {
            self::pack_credit_box( $bxtr_cp_product, $granted );
            return;
        }
    }

    public static function redeemable_product_credit_box( $product, $required ) {
        $balance     = BXTR_CP_Ledger::get_balance( get_current_user_id() );
        $can_use     = $balance >= $required;
        $price_html  = self::get_product_price_html( $product );
        $packs_html  = self::get_credit_pack_links_html();
        $data = array(
            'credit_balance'   => $balance,
            'credits_required' => $required,
            'product_name'     => $product->get_name(),
            'credit_label'     => BXTR_CP_Settings::credit_label( $required ),
            'credit_label_plural' => BXTR_CP_Settings::get( 'credit_label_plural' ),
        );
        ?>
        <div class="bxtr-cp-product-credit-box bxtr-cp-product-credit-box--redeemable" style="<?php echo esc_attr( BXTR_CP_Settings::frontend_style_attr() ); ?>">
            <div class="bxtr-cp-product-credit-box__title"><span><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'redeemable_title' ), $data ) ); ?></span><?php echo BXTR_CP_Settings::safe_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <p><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'redeemable_message' ), $data ) ); ?></p>
            <p><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'balance_message' ), $data ) ); ?></p>

            <?php if ( $packs_html ) : ?>
                <p class="bxtr-cp-muted">
                    <?php echo esc_html( sprintf(
                        /* translators: %s: plural credit label. */
                        __( 'Need more %s?', 'credit-packs-for-woocommerce' ), BXTR_CP_Settings::get( 'credit_label_plural' ) ) ); ?>
                    <?php echo wp_kses_post( $packs_html ); ?>.
                </p>
            <?php endif; ?>

            <?php if ( $can_use ) : ?>
                <label class="bxtr-cp-use-credits-label">
                    <input type="hidden" name="bxtr_cp_use_credits_present" value="1">
                    <input type="checkbox" name="bxtr_cp_use_credits" value="yes" checked>
                    <?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'checkbox_label' ), $data ) ); ?>
                </label>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function pack_credit_box( $product, $granted ) {
        $discount = self::get_discount_percent( $product );
        $data = array(
            'credits_granted' => $granted,
            'credit_label'    => BXTR_CP_Settings::credit_label( $granted ),
            'credit_label_plural' => BXTR_CP_Settings::get( 'credit_label_plural' ),
            'product_name'    => $product->get_name(),
        );
        ?>
        <div class="bxtr-cp-product-credit-box bxtr-cp-product-credit-box--pack" style="<?php echo esc_attr( BXTR_CP_Settings::frontend_style_attr() ); ?>">
            <div class="bxtr-cp-product-credit-box__title"><span><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'pack_title' ), $data ) ); ?></span><?php echo BXTR_CP_Settings::safe_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <p><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'pack_message' ), $data ) ); ?></p>
            <p><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'pack_redeem_message' ), $data ) ); ?></p>
            <?php if ( $discount ) : ?>
                <p><span class="bxtr-cp-save-text"><?php echo esc_html( sprintf(
                        /* translators: %d: discount percentage. */
                        __( 'Save %d%%', 'credit-packs-for-woocommerce' ), $discount ) ); ?></span> <?php esc_html_e( 'compared with purchasing individually.', 'credit-packs-for-woocommerce' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function get_product_price_html( $product ) {
        $price_html = $product->get_price_html();
        if ( $price_html ) return $price_html;

        $price = $product->get_price();
        return '' !== $price ? wc_price( $price ) : esc_html__( 'the normal price', 'credit-packs-for-woocommerce' );
    }

    private static function get_discount_percent( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return 0;

        $regular = (float) $product->get_regular_price();
        $sale    = (float) $product->get_sale_price();

        if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) return 0;

        return max( 0, (int) round( ( ( $regular - $sale ) / $regular ) * 100 ) );
    }

    private static function get_credit_pack_products( $limit = 6 ) {
        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $limit ),
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small product lookup for credit pack links.
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'   => '_bxtr_cp_credit_type',
                    'value' => 'pack',
                ),
                array(
                    'key'     => '_bxtr_cp_credits_granted',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ),
            ),
        ) );

        $products = array();
        foreach ( $query->posts as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;
            $products[] = $product;
        }

        usort( $products, function( $a, $b ) {
            return BXTR_CP_Products::get_credits_granted( $a->get_id() ) <=> BXTR_CP_Products::get_credits_granted( $b->get_id() );
        } );

        return $products;
    }

    private static function get_credit_pack_links_html() {
        $products = self::get_credit_pack_products();
        if ( empty( $products ) ) return '';

        $links = array();
        foreach ( $products as $product ) {
            $title    = get_the_title( $product->get_id() );
            $discount = self::get_discount_percent( $product );

            $link = '<a class="bxtr-cp-pack-inline-link" href="' . esc_url( get_permalink( $product->get_id() ) ) . '">' . esc_html( $title ) . '</a>';
            if ( $discount ) {
                $link .= ' <span class="bxtr-cp-save-text">' . esc_html( sprintf(
                        /* translators: %d: discount percentage. */
                        __( 'Save %d%%', 'credit-packs-for-woocommerce' ), $discount ) ) . '</span>';
            }
            $links[] = $link;
        }

        return implode( ', ', $links );
    }

    public static function add_cart_item_data( $cart_item_data, $product_id ) {
        $required = BXTR_CP_Products::get_credits_required( $product_id );
        if ( $required > 0 ) {
            // The checkbox is submitted through the WooCommerce add-to-cart form. WooCommerce validates the request before this filter runs.
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( isset( $_POST['bxtr_cp_use_credits_present'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $cart_item_data['bxtr_cp_use_credits'] = isset( $_POST['bxtr_cp_use_credits'] ) ? 'yes' : 'no';
            } else {
                $cart_item_data['bxtr_cp_use_credits'] = 'yes';
            }
        }
        return $cart_item_data;
    }

    public static function apply_credit_pricing( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) return;

        $remaining_balance = BXTR_CP_Ledger::get_balance( get_current_user_id() );

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
            if ( ! $product_id || empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) continue;

            $required = BXTR_CP_Products::get_credits_required( $product_id );
            $use      = ( $cart_item['bxtr_cp_use_credits'] ?? 'no' ) === 'yes';
            $qty      = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
            $total_required = $required * $qty;

            if ( $required > 0 && $use && $remaining_balance >= $total_required ) {
                $cart_item['data']->set_price( 0 );
                $cart->cart_contents[ $cart_item_key ]['bxtr_cp_credits_used'] = $total_required;
                $remaining_balance -= $total_required;
            } else {
                unset( $cart->cart_contents[ $cart_item_key ]['bxtr_cp_credits_used'] );
            }
        }
    }

    public static function show_cart_item_data( $item_data, $cart_item ) {
        $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

        if ( $product_id ) {
            $granted = BXTR_CP_Products::get_credits_granted( $product_id );
            $qty     = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

            if ( $granted > 0 ) {
                $item_data[] = array(
                    'name'  => __( 'Credits Granted', 'credit-packs-for-woocommerce' ),
                    'value' => sprintf(
                        /* translators: %d: number of credits granted. */
                        __( '%d credit(s) will be added when the order is completed', 'credit-packs-for-woocommerce' ),
                        $granted * $qty
                    ),
                );
            }
        }

        if ( ( $cart_item['bxtr_cp_use_credits'] ?? 'no' ) === 'yes' ) {
            $required = $product_id ? BXTR_CP_Products::get_credits_required( $product_id ) : 0;
            $qty      = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
            $credits  = ! empty( $cart_item['bxtr_cp_credits_used'] ) ? (int) $cart_item['bxtr_cp_credits_used'] : ( $required * $qty );

            $item_data[] = array(
                'name'  => __( 'Credits Used', 'credit-packs-for-woocommerce' ),
                'value' => $credits > 0
                    ? sprintf(
                        /* translators: %d: number of credits used. */
                        __( '%d Credit(s) will be used for this product', 'credit-packs-for-woocommerce' ),
                        $credits
                    )
                    : __( 'Requested, but not enough Credits available', 'credit-packs-for-woocommerce' ),
            );
        }
        return $item_data;
    }

    public static function get_cart_credits_used() {
        if ( ! WC()->cart ) return 0;
        $total = 0;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['bxtr_cp_credits_used'] ) ) {
                $total += (int) $item['bxtr_cp_credits_used'];
            }
        }
        return $total;
    }

    public static function cart_credit_notice() {
        $total = self::get_cart_credits_used();
        if ( $total > 0 ) {
            wc_print_notice( sprintf(
                /* translators: %d: number of credits used in the cart. */
                __( 'Credit Packs for WooCommerce: %d Credit(s) will be used for this order.', 'credit-packs-for-woocommerce' ),
                $total
            ), 'notice' );
        }
    }

    public static function cart_totals_credit_note() {
        $total = self::get_cart_credits_used();
        if ( $total <= 0 ) return;
        echo '<tr class="bxtr-cp-cart-credit-total"><th>' . esc_html__( 'Credits Used', 'credit-packs-for-woocommerce' ) . '</th><td data-title="' . esc_attr__( 'Credits Used', 'credit-packs-for-woocommerce' ) . '"><strong>' . esc_html( $total ) . '</strong></td></tr>';
    }

    public static function create_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['bxtr_cp_credits_used'] ) ) {
            $credits = (int) $values['bxtr_cp_credits_used'];
            $item->add_meta_data( '_bxtr_cp_credits_used', $credits, true );
            $item->add_meta_data( __( 'Credits Used', 'credit-packs-for-woocommerce' ), $credits, true );
        }

        $product_id = isset( $values['product_id'] ) ? absint( $values['product_id'] ) : 0;
        if ( $product_id ) {
            $granted = BXTR_CP_Products::get_credits_granted( $product_id );
            if ( $granted > 0 ) {
                $qty = isset( $values['quantity'] ) ? max( 1, (int) $values['quantity'] ) : 1;
                $credits = $granted * $qty;
                $item->add_meta_data( '_bxtr_cp_credits_granted', $credits, true );
                $item->add_meta_data( __( 'Credits Granted', 'credit-packs-for-woocommerce' ), sprintf(
                    /* translators: %d: number of credits granted when the order is completed. */
                    __( '%d Credit(s) on order completion', 'credit-packs-for-woocommerce' ),
                    $credits
                ), true );
            }
        }
    }

    public static function hide_internal_order_item_meta( $hidden_meta ) {
        $hidden_meta[] = '_bxtr_cp_credits_used';
        $hidden_meta[] = '_bxtr_cp_credits_granted';
        $hidden_meta[] = '_bxtr_cp_credits_returned';
        return array_unique( $hidden_meta );
    }

    public static function handle_paid_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        self::grant_pack_credits( $order );
        self::redeem_product_credits( $order );
    }

    private static function grant_pack_credits( $order ) {
        $flag = '_bxtr_cp_granted_credits';
        if ( $order->get_meta( $flag ) ) return;

        $user_id = $order->get_user_id();
        if ( ! $user_id ) return;

        $total_granted = 0;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( ! $product_id ) continue;

            $granted = BXTR_CP_Products::get_credits_granted( $product_id );
            if ( $granted <= 0 ) continue;

            $qty = max( 1, (int) $item->get_quantity() );
            $credits = $granted * $qty;
            $expiry_days = BXTR_CP_Products::get_expiry_days( $product_id );
            $expiry_date = $expiry_days > 0 ? gmdate( 'Y-m-d', strtotime( '+' . $expiry_days . ' days', current_time( 'timestamp' ) ) ) : null;

            BXTR_CP_Ledger::add_entry( array(
                'user_id'       => $user_id,
                'order_id'      => $order->get_id(),
                'product_id'    => $product_id,
                'change_amount' => $credits,
                'entry_type'    => 'grant',
                'expiry_date'   => $expiry_date,
                'note'          => sprintf( 'Credits granted from order #%d: %s', $order->get_id(), $item->get_name() ),
                'created_by'    => null,
            ) );

            $total_granted += $credits;
        }

        if ( $total_granted > 0 ) {
            $order->update_meta_data( '_bxtr_cp_total_credits_granted', $total_granted );
        }

        $order->update_meta_data( $flag, current_time( 'mysql' ) );
        $order->save();
    }

    private static function redeem_product_credits( $order ) {
        $flag = '_bxtr_cp_redeemed_credits';
        if ( $order->get_meta( $flag ) ) return;

        $user_id = $order->get_user_id();
        if ( ! $user_id ) return;

        $total_used = 0;

        foreach ( $order->get_items() as $item ) {
            $credits_used = (int) $item->get_meta( '_bxtr_cp_credits_used', true );
            if ( $credits_used <= 0 ) continue;

            BXTR_CP_Ledger::add_entry( array(
                'user_id'       => $user_id,
                'order_id'      => $order->get_id(),
                'product_id'    => $item->get_product_id(),
                'change_amount' => -$credits_used,
                'entry_type'    => 'redeem',
                'expiry_date'   => null,
                'note'          => sprintf( 'Credits redeemed for order #%d: %s', $order->get_id(), $item->get_name() ),
                'created_by'    => null,
            ) );

            $total_used += $credits_used;
        }

        if ( $total_used > 0 ) {
            $order->update_meta_data( '_bxtr_cp_total_credits_redeemed', $total_used );
        }

        $order->update_meta_data( $flag, current_time( 'mysql' ) );
        $order->save();
    }

    private static function get_redeemed_credits_from_order( $order ) {
        $redeemed = (int) $order->get_meta( '_bxtr_cp_total_credits_redeemed', true );
        if ( $redeemed > 0 ) return $redeemed;

        $total = 0;
        foreach ( $order->get_items() as $item ) {
            $total += (int) $item->get_meta( '_bxtr_cp_credits_used', true );
        }
        return $total;
    }

    private static function add_refund_display_meta( $order, $refund, $credits_returned ) {
        if ( ! $refund || $credits_returned <= 0 ) return;

        $refund->update_meta_data( '_bxtr_cp_credits_returned', $credits_returned );

        $reason = $refund->get_reason();
        $label  = sprintf(
            /* translators: %d: number of credits returned. */
            __( 'Credits Returned: %d', 'credit-packs-for-woocommerce' ), $credits_returned );

        if ( false === strpos( (string) $reason, 'Credits Returned' ) ) {
            $refund->set_reason( trim( $reason ? $reason . ' | ' . $label : $label ) );
        }

        foreach ( $refund->get_items() as $refund_item ) {
            $refund_item->add_meta_data( __( 'Credits Returned', 'credit-packs-for-woocommerce' ), $credits_returned, true );
            $refund_item->save();
        }

        $refund->save();
        $order->add_order_note( sprintf(
            /* translators: %d: number of credits returned to the customer. */
            __( 'Credit Packs for WooCommerce: %d Credit(s) returned to the customer.', 'credit-packs-for-woocommerce' ), $credits_returned ) );
    }

    public static function handle_cancelled_order( $order_id, $refund = null ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return 0;

        $user_id = $order->get_user_id();
        if ( ! $user_id ) return 0;

        $flag = '_bxtr_cp_cancel_reversed';
        if ( $order->get_meta( $flag ) ) return 0;

        $returned_total = 0;

        $redeemed = self::get_redeemed_credits_from_order( $order );
        if ( $redeemed > 0 ) {
            BXTR_CP_Ledger::add_entry( array(
                'user_id'       => $user_id,
                'order_id'      => $order->get_id(),
                'change_amount' => $redeemed,
                'entry_type'    => 'refund',
                'note'          => sprintf( 'Credits returned because order #%d was cancelled/refunded.', $order->get_id() ),
                'created_by'    => null,
            ) );
            $returned_total += $redeemed;
        }

        $granted = (int) $order->get_meta( '_bxtr_cp_total_credits_granted', true );
        if ( $granted > 0 ) {
            $current_balance = BXTR_CP_Ledger::get_balance( $user_id );

            if ( $current_balance >= $granted ) {
                BXTR_CP_Ledger::add_entry( array(
                    'user_id'       => $user_id,
                    'order_id'      => $order->get_id(),
                    'change_amount' => -$granted,
                    'entry_type'    => 'grant_reversal',
                    'note'          => sprintf( 'Granted credits reversed because order #%d was cancelled/refunded.', $order->get_id() ),
                    'created_by'    => null,
                ) );
            } else {
                BXTR_CP_Ledger::add_entry( array(
                    'user_id'       => $user_id,
                    'order_id'      => $order->get_id(),
                    'change_amount' => 0,
                    'entry_type'    => 'refund_review',
                    'note'          => sprintf( 'Refund review required for order #%d. This pack granted %d credits, but the user only has %d available, so credits appear to have been spent.', $order->get_id(), $granted, $current_balance ),
                    'created_by'    => get_current_user_id() ?: null,
                ) );
                $order->add_order_note( sprintf( 'Credit Packs for WooCommerce: refund review required. This pack granted %d credits, but the user only has %d available. Do not process automatically without manual review.', $granted, $current_balance ) );
            }
        }

        if ( $returned_total > 0 ) {
            $order->update_meta_data( '_bxtr_cp_total_credits_returned', $returned_total );
            if ( $refund ) {
                self::add_refund_display_meta( $order, $refund, $returned_total );
            }
        }

        $order->update_meta_data( $flag, current_time( 'mysql' ) );
        $order->save();

        return $returned_total;
    }

    public static function admin_refund_credit_guidance() {
        if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, 'shop_order' ) ) {
            return;
        }

        $order_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $redeemed = self::get_redeemed_credits_from_order( $order );
        $granted  = (int) $order->get_meta( '_bxtr_cp_total_credits_granted', true );
        if ( $redeemed <= 0 && $granted <= 0 ) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var refundArea = document.querySelector('.wc-order-refund-items, .wc-order-data-row-toggle');
            if (!refundArea || document.querySelector('.bxtr-cp-refund-guidance')) return;
            var note = document.createElement('p');
            note.className = 'bxtr-cp-refund-guidance description';
            note.style.margin = '8px 0';
            note.textContent = 'Credit Packs: this order includes credit activity. Use full refunds where possible. Credits are returned or reversed through the Credit Packs ledger when a full refund or cancellation is processed.';
            refundArea.parentNode.insertBefore(note, refundArea);

            document.querySelectorAll('label, th, td, span').forEach(function(el){
                if (el.childNodes.length === 1 && el.textContent && el.textContent.trim() === 'Refund amount') {
                    el.textContent = 'Refund credit amount';
                }
            });
        });
        </script>
        <?php
    }

    public static function handle_order_refund_created( $order_id, $refund_id ) {
        $order = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );
        if ( ! $order || ! $refund ) return;

        $order_total = (float) $order->get_total();
        $refund_total = abs( (float) $refund->get_amount() );

        // Credit-paid product orders often have a monetary total of 0.
        // In that case, any created refund is treated as a full credit refund intention.
        $is_full_refund = ( 0.0 === $order_total ) || ( $refund_total >= $order_total );

        if ( ! $is_full_refund ) {
            $user_id = $order->get_user_id();
            if ( $user_id ) {
                BXTR_CP_Ledger::add_entry( array(
                    'user_id'       => $user_id,
                    'order_id'      => $order->get_id(),
                    'change_amount' => 0,
                    'entry_type'    => 'refund_review',
                    'note'          => sprintf( 'Partial refund detected for order #%d. Credit Packs for WooCommerce does not process partial credit refunds automatically.', $order->get_id() ),
                    'created_by'    => get_current_user_id() ?: null,
                ) );
            }
            $order->add_order_note( 'Credit Packs for WooCommerce: partial refund detected. No credits were changed automatically.' );
            return;
        }

        $returned = self::handle_cancelled_order( $order_id, $refund );

        // If the order was already reversed before this refund row was created, still annotate the refund for admin clarity.
        if ( $returned <= 0 ) {
            $already_returned = (int) $order->get_meta( '_bxtr_cp_total_credits_returned', true );
            if ( $already_returned > 0 ) {
                self::add_refund_display_meta( $order, $refund, $already_returned );
            }
        }
    }


}
