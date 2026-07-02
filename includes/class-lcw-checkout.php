<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Checkout {
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

        add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'product_credit_box' ), 25 );
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
    }

    public static function assets() {
        wp_enqueue_style( 'lcw-frontend', LCW_PLUGIN_URL . 'assets/css/frontend.css', array(), LCW_VERSION );
    }

    public static function product_credit_box() {
        if ( ! is_user_logged_in() ) return;
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return;

        $product_id = $product->get_id();
        $required   = LCW_Products::get_credits_required( $product_id );
        $granted    = LCW_Products::get_credits_granted( $product_id );

        if ( $required > 0 ) {
            self::lesson_credit_box( $product, $required );
            return;
        }

        if ( $granted > 0 ) {
            self::pack_credit_box( $product, $granted );
            return;
        }
    }

    private static function lesson_credit_box( $product, $required ) {
        $balance     = LCW_Ledger::get_balance( get_current_user_id() );
        $can_use     = $balance >= $required;
        $price_html  = self::get_product_price_html( $product );
        $packs_html  = self::get_credit_pack_links_html();
        ?>
        <div class="lcw-product-credit-box">
            <p class="lcw-product-credit-box__title"><?php esc_html_e( 'Pay with Credits', 'lesson-credit-wallet' ); ?></p>
            <p><?php echo wp_kses_post( sprintf( _n( 'This lesson can be booked for %s or %d Lesson Credit.', 'This lesson can be booked for %s or %d Lesson Credits.', $required, 'lesson-credit-wallet' ), $price_html, $required ) ); ?></p>
            <p><?php echo esc_html( sprintf( _n( 'You currently have %d Lesson Credit available.', 'You currently have %d Lesson Credits available.', $balance, 'lesson-credit-wallet' ), $balance ) ); ?></p>

            <?php if ( $packs_html ) : ?>
                <p>
                    <?php esc_html_e( 'Available Credit Packs:', 'lesson-credit-wallet' ); ?>
                    <?php echo wp_kses_post( $packs_html ); ?>.
                    <?php esc_html_e( 'Or simply click Book Now to pay as normal.', 'lesson-credit-wallet' ); ?>
                </p>
            <?php else : ?>
                <p><?php esc_html_e( 'Simply click Book Now to pay as normal.', 'lesson-credit-wallet' ); ?></p>
            <?php endif; ?>

            <?php if ( $can_use ) : ?>
                <label class="lcw-use-credits-label">
                    <input type="checkbox" name="lcw_use_credits" value="yes" checked>
                    <?php esc_html_e( 'Use my Lesson Credits for this booking', 'lesson-credit-wallet' ); ?>
                </label>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function pack_credit_box( $product, $granted ) {
        $discount = self::get_discount_percent( $product );
        ?>
        <div class="lcw-product-credit-box lcw-product-credit-box--pack">
            <p><strong><?php esc_html_e( "What's included", 'lesson-credit-wallet' ); ?></strong></p>
            <p><?php echo wp_kses_post( sprintf( _n( 'Includes <strong>%d × 1 Hour Session</strong> as <strong>%d Lesson Credit</strong>.', 'Includes <strong>%d × 1 Hour Sessions</strong> as <strong>%d Lesson Credits</strong>.', $granted, 'lesson-credit-wallet' ), $granted, $granted ) ); ?></p>
            <p><?php esc_html_e( 'Redeem Lesson Credits against any eligible lesson booking.', 'lesson-credit-wallet' ); ?></p>
            <?php if ( $discount ) : ?>
                <p><span class="lcw-save-text"><?php echo esc_html( sprintf( __( 'Save %d%%', 'lesson-credit-wallet' ), $discount ) ); ?></span> <?php esc_html_e( 'compared with booking sessions individually.', 'lesson-credit-wallet' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function get_product_price_html( $product ) {
        $price_html = $product->get_price_html();
        if ( $price_html ) return $price_html;

        $price = $product->get_price();
        return '' !== $price ? wc_price( $price ) : esc_html__( 'the normal price', 'lesson-credit-wallet' );
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
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'   => '_lcw_credit_type',
                    'value' => 'pack',
                ),
                array(
                    'key'     => '_lcw_credits_granted',
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
            return LCW_Products::get_credits_granted( $a->get_id() ) <=> LCW_Products::get_credits_granted( $b->get_id() );
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

            $link = '<a class="lcw-pack-inline-link" href="' . esc_url( get_permalink( $product->get_id() ) ) . '">' . esc_html( $title ) . '</a>';
            if ( $discount ) {
                $link .= ' <span class="lcw-save-text">' . esc_html( sprintf( __( 'Save %d%%', 'lesson-credit-wallet' ), $discount ) ) . '</span>';
            }
            $links[] = $link;
        }

        return implode( ', ', $links );
    }

    public static function add_cart_item_data( $cart_item_data, $product_id ) {
        $required = LCW_Products::get_credits_required( $product_id );
        if ( $required > 0 ) {
            $cart_item_data['lcw_use_credits'] = isset( $_POST['lcw_use_credits'] ) ? 'yes' : 'no';
        }
        return $cart_item_data;
    }

    public static function apply_credit_pricing( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( ! is_user_logged_in() ) return;
        if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) return;

        $remaining_balance = LCW_Ledger::get_balance( get_current_user_id() );

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
            if ( ! $product_id || empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) continue;

            $required = LCW_Products::get_credits_required( $product_id );
            $use      = ( $cart_item['lcw_use_credits'] ?? 'no' ) === 'yes';
            $qty      = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
            $total_required = $required * $qty;

            if ( $required > 0 && $use && $remaining_balance >= $total_required ) {
                $cart_item['data']->set_price( 0 );
                $cart->cart_contents[ $cart_item_key ]['lcw_credits_used'] = $total_required;
                $remaining_balance -= $total_required;
            } else {
                unset( $cart->cart_contents[ $cart_item_key ]['lcw_credits_used'] );
            }
        }
    }

    public static function show_cart_item_data( $item_data, $cart_item ) {
        $product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

        if ( $product_id ) {
            $granted = LCW_Products::get_credits_granted( $product_id );
            $qty     = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;

            if ( $granted > 0 ) {
                $item_data[] = array(
                    'name'  => __( 'Lesson Credits Granted', 'lesson-credit-wallet' ),
                    'value' => sprintf( __( '%d Lesson Credit(s) will be added when the order is completed', 'lesson-credit-wallet' ), $granted * $qty ),
                );
            }
        }

        if ( ( $cart_item['lcw_use_credits'] ?? 'no' ) === 'yes' ) {
            $item_data[] = array(
                'name'  => __( 'Lesson Credits', 'lesson-credit-wallet' ),
                'value' => ! empty( $cart_item['lcw_credits_used'] )
                    ? sprintf( __( '%d Lesson Credit(s) will be used', 'lesson-credit-wallet' ), (int) $cart_item['lcw_credits_used'] )
                    : __( 'Requested, but not enough Lesson Credits available', 'lesson-credit-wallet' ),
            );
        }
        return $item_data;
    }

    public static function get_cart_credits_used() {
        if ( ! WC()->cart ) return 0;
        $total = 0;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['lcw_credits_used'] ) ) {
                $total += (int) $item['lcw_credits_used'];
            }
        }
        return $total;
    }

    public static function cart_credit_notice() {
        $total = self::get_cart_credits_used();
        if ( $total > 0 ) {
            wc_print_notice( sprintf( __( 'Lesson Credit Wallet: %d Lesson Credit(s) will be used for this order.', 'lesson-credit-wallet' ), $total ), 'notice' );
        }
    }

    public static function cart_totals_credit_note() {
        $total = self::get_cart_credits_used();
        if ( $total <= 0 ) return;
        echo '<tr class="lcw-cart-credit-total"><th>' . esc_html__( 'Lesson Credits Used', 'lesson-credit-wallet' ) . '</th><td data-title="' . esc_attr__( 'Lesson Credits Used', 'lesson-credit-wallet' ) . '"><strong>' . esc_html( $total ) . '</strong></td></tr>';
    }

    public static function create_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['lcw_credits_used'] ) ) {
            $credits = (int) $values['lcw_credits_used'];
            $item->add_meta_data( '_lcw_credits_used', $credits, true );
            $item->add_meta_data( __( 'Lesson Credits Used', 'lesson-credit-wallet' ), $credits, true );
        }

        $product_id = isset( $values['product_id'] ) ? absint( $values['product_id'] ) : 0;
        if ( $product_id ) {
            $granted = LCW_Products::get_credits_granted( $product_id );
            if ( $granted > 0 ) {
                $qty = isset( $values['quantity'] ) ? max( 1, (int) $values['quantity'] ) : 1;
                $credits = $granted * $qty;
                $item->add_meta_data( '_lcw_credits_granted', $credits, true );
                $item->add_meta_data( __( 'Lesson Credits Granted', 'lesson-credit-wallet' ), sprintf( __( '%d Lesson Credit(s) on order completion', 'lesson-credit-wallet' ), $credits ), true );
            }
        }
    }

    public static function hide_internal_order_item_meta( $hidden_meta ) {
        $hidden_meta[] = '_lcw_credits_used';
        $hidden_meta[] = '_lcw_credits_granted';
        $hidden_meta[] = '_lcw_credits_returned';
        return array_unique( $hidden_meta );
    }

    public static function handle_paid_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        self::grant_pack_credits( $order );
        self::redeem_lesson_credits( $order );
    }

    private static function grant_pack_credits( $order ) {
        $flag = '_lcw_granted_credits';
        if ( $order->get_meta( $flag ) ) return;

        $user_id = $order->get_user_id();
        if ( ! $user_id ) return;

        $total_granted = 0;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( ! $product_id ) continue;

            $granted = LCW_Products::get_credits_granted( $product_id );
            if ( $granted <= 0 ) continue;

            $qty = max( 1, (int) $item->get_quantity() );
            $credits = $granted * $qty;
            $expiry_days = LCW_Products::get_expiry_days( $product_id );
            $expiry_date = $expiry_days > 0 ? gmdate( 'Y-m-d', strtotime( '+' . $expiry_days . ' days', current_time( 'timestamp' ) ) ) : null;

            LCW_Ledger::add_entry( array(
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
            $order->update_meta_data( '_lcw_total_credits_granted', $total_granted );
        }

        $order->update_meta_data( $flag, current_time( 'mysql' ) );
        $order->save();
    }

    private static function redeem_lesson_credits( $order ) {
        $flag = '_lcw_redeemed_credits';
        if ( $order->get_meta( $flag ) ) return;

        $user_id = $order->get_user_id();
        if ( ! $user_id ) return;

        $total_used = 0;

        foreach ( $order->get_items() as $item ) {
            $credits_used = (int) $item->get_meta( '_lcw_credits_used', true );
            if ( $credits_used <= 0 ) continue;

            LCW_Ledger::add_entry( array(
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
            $order->update_meta_data( '_lcw_total_credits_redeemed', $total_used );
        }

        $order->update_meta_data( $flag, current_time( 'mysql' ) );
        $order->save();
    }

    private static function get_redeemed_credits_from_order( $order ) {
        $redeemed = (int) $order->get_meta( '_lcw_total_credits_redeemed', true );
        if ( $redeemed > 0 ) return $redeemed;

        $total = 0;
        foreach ( $order->get_items() as $item ) {
            $total += (int) $item->get_meta( '_lcw_credits_used', true );
        }
        return $total;
    }

    private static function add_refund_display_meta( $order, $refund, $credits_returned ) {
        if ( ! $refund || $credits_returned <= 0 ) return;

        $refund->update_meta_data( '_lcw_credits_returned', $credits_returned );

        $reason = $refund->get_reason();
        $label  = sprintf( __( 'Lesson Credits Returned: %d', 'lesson-credit-wallet' ), $credits_returned );

        if ( false === strpos( (string) $reason, 'Lesson Credits Returned' ) ) {
            $refund->set_reason( trim( $reason ? $reason . ' | ' . $label : $label ) );
        }

        foreach ( $refund->get_items() as $refund_item ) {
            $refund_item->add_meta_data( __( 'Lesson Credits Returned', 'lesson-credit-wallet' ), $credits_returned, true );
            $refund_item->save();
        }

        $refund->save();
        $order->add_order_note( sprintf( __( 'Lesson Credit Wallet: %d Lesson Credit(s) returned to the customer.', 'lesson-credit-wallet' ), $credits_returned ) );
    }

    public static function handle_cancelled_order( $order_id, $refund = null ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return 0;

        $user_id = $order->get_user_id();
        if ( ! $user_id ) return 0;

        $flag = '_lcw_cancel_reversed';
        if ( $order->get_meta( $flag ) ) return 0;

        $returned_total = 0;

        $redeemed = self::get_redeemed_credits_from_order( $order );
        if ( $redeemed > 0 ) {
            LCW_Ledger::add_entry( array(
                'user_id'       => $user_id,
                'order_id'      => $order->get_id(),
                'change_amount' => $redeemed,
                'entry_type'    => 'refund',
                'note'          => sprintf( 'Credits returned because order #%d was cancelled/refunded.', $order->get_id() ),
                'created_by'    => null,
            ) );
            $returned_total += $redeemed;
        }

        $granted = (int) $order->get_meta( '_lcw_total_credits_granted', true );
        if ( $granted > 0 ) {
            $current_balance = LCW_Ledger::get_balance( $user_id );

            if ( $current_balance >= $granted ) {
                LCW_Ledger::add_entry( array(
                    'user_id'       => $user_id,
                    'order_id'      => $order->get_id(),
                    'change_amount' => -$granted,
                    'entry_type'    => 'grant_reversal',
                    'note'          => sprintf( 'Granted credits reversed because order #%d was cancelled/refunded.', $order->get_id() ),
                    'created_by'    => null,
                ) );
            } else {
                LCW_Ledger::add_entry( array(
                    'user_id'       => $user_id,
                    'order_id'      => $order->get_id(),
                    'change_amount' => 0,
                    'entry_type'    => 'refund_review',
                    'note'          => sprintf( 'Refund review required for order #%d. This pack granted %d credits, but the user only has %d available, so credits appear to have been spent.', $order->get_id(), $granted, $current_balance ),
                    'created_by'    => get_current_user_id() ?: null,
                ) );
                $order->add_order_note( sprintf( 'Lesson Credit Wallet: refund review required. This pack granted %d credits, but the user only has %d available. Do not process automatically without manual review.', $granted, $current_balance ) );
            }
        }

        if ( $returned_total > 0 ) {
            $order->update_meta_data( '_lcw_total_credits_returned', $returned_total );
            if ( $refund ) {
                self::add_refund_display_meta( $order, $refund, $returned_total );
            }
        }

        $order->update_meta_data( $flag, current_time( 'mysql' ) );
        $order->save();

        return $returned_total;
    }
    public static function handle_order_refund_created( $order_id, $refund_id ) {
        $order = wc_get_order( $order_id );
        $refund = wc_get_order( $refund_id );
        if ( ! $order || ! $refund ) return;

        $order_total = (float) $order->get_total();
        $refund_total = abs( (float) $refund->get_amount() );

        // Credit-paid lesson orders often have a monetary total of 0.
        // In that case, any created refund is treated as a full credit refund intention.
        $is_full_refund = ( 0.0 === $order_total ) || ( $refund_total >= $order_total );

        if ( ! $is_full_refund ) {
            $user_id = $order->get_user_id();
            if ( $user_id ) {
                LCW_Ledger::add_entry( array(
                    'user_id'       => $user_id,
                    'order_id'      => $order->get_id(),
                    'change_amount' => 0,
                    'entry_type'    => 'refund_review',
                    'note'          => sprintf( 'Partial refund detected for order #%d. Lesson Credit Wallet does not process partial credit refunds automatically.', $order->get_id() ),
                    'created_by'    => get_current_user_id() ?: null,
                ) );
            }
            $order->add_order_note( 'Lesson Credit Wallet: partial refund detected. No credits were changed automatically.' );
            return;
        }

        $returned = self::handle_cancelled_order( $order_id, $refund );

        // If the order was already reversed before this refund row was created, still annotate the refund for admin clarity.
        if ( $returned <= 0 ) {
            $already_returned = (int) $order->get_meta( '_lcw_total_credits_returned', true );
            if ( $already_returned > 0 ) {
                self::add_refund_display_meta( $order, $refund, $already_returned );
            }
        }
    }


}
