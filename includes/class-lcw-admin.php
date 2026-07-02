<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_manual_adjustment' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( LCW_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
    }

    public static function assets( $hook ) {
        if ( false === strpos( $hook, 'lesson-credit-wallet' ) ) return;

        wp_enqueue_style( 'lcw-admin', LCW_PLUGIN_URL . 'assets/css/admin.css', array(), LCW_VERSION );

        if ( class_exists( 'WooCommerce' ) ) {
            wp_enqueue_script( 'wc-enhanced-select' );
            wp_enqueue_style( 'woocommerce_admin_styles' );
        }
    }


    public static function plugin_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=lesson-credit-wallet' ) ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public static function plugin_row_meta( $links, $file ) {
        if ( plugin_basename( LCW_PLUGIN_FILE ) !== $file ) {
            return $links;
        }

        $links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=lesson-credit-wallet#lcw-changelog' ) ) . '">View details</a>';
        return $links;
    }

    public static function admin_menu() {
        add_menu_page(
            'Lesson Credit Wallet',
            'Lesson Credits',
            'manage_woocommerce',
            'lesson-credit-wallet',
            array( __CLASS__, 'render_page' ),
            'dashicons-tickets-alt',
            56
        );
    }

    public static function handle_manual_adjustment() {
        if ( empty( $_POST['lcw_manual_adjustment'] ) ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        check_admin_referer( 'lcw_manual_adjustment' );

        $user_id = isset( $_POST['lcw_user_id'] ) ? absint( wp_unslash( $_POST['lcw_user_id'] ) ) : 0;
        $amount  = isset( $_POST['lcw_amount'] ) ? (int) wp_unslash( $_POST['lcw_amount'] ) : 0;
        $note    = isset( $_POST['lcw_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lcw_note'] ) ) : '';
        $expiry  = isset( $_POST['lcw_expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lcw_expiry_date'] ) ) : '';

        if ( $user_id && 0 !== $amount ) {
            LCW_Ledger::add_entry( array(
                'user_id'       => $user_id,
                'change_amount' => $amount,
                'entry_type'    => 'manual',
                'expiry_date'   => $expiry ?: null,
                'note'          => $note ?: 'Manual credit adjustment.',
            ) );

            wp_safe_redirect( add_query_arg( array(
                'page'        => 'lesson-credit-wallet',
                'lcw_updated' => 1,
                'lcw_user'    => $user_id,
            ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    private static function dependency_label( $ok ) {
        return $ok ? '<span class="lcw-status lcw-status--ok">Active</span>' : '<span class="lcw-status lcw-status--warn">Not detected</span>';
    }

    private static function render_user_option( $user_id ) {
        if ( ! $user_id ) return;
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        echo '<option value="' . esc_attr( $user_id ) . '" selected="selected">' . esc_html( $user->display_name . ' (' . $user->user_email . ')' ) . '</option>';
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        $users          = LCW_Ledger::get_users_with_balances( 150 );
        $entries        = LCW_Ledger::get_recent_entries( 100 );
        $selected_user  = isset( $_GET['lcw_user'] ) ? absint( $_GET['lcw_user'] ) : 0;
        ?>
        <div class="wrap lcw-admin">
            <h1>Lesson Credit Wallet</h1>
            <p class="lcw-intro">A prepaid lesson credit system for WooCommerce bookings and Tutor LMS dashboards.</p>

            <?php if ( isset( $_GET['lcw_updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Credit balance updated.</p></div>
            <?php endif; ?>

            <div class="lcw-grid lcw-grid--three">
                <div class="lcw-card">
                    <h2>System Status</h2>
                    <ul class="lcw-checklist">
                        <li><strong>WooCommerce:</strong> <?php echo wp_kses_post( self::dependency_label( class_exists( 'WooCommerce' ) ) ); ?></li>
                        <li><strong>WooCommerce Appointments:</strong> <?php echo wp_kses_post( self::dependency_label( class_exists( 'WC_Appointments' ) || class_exists( 'WC_Appointment' ) ) ); ?></li>
                        <li><strong>Tutor LMS:</strong> <?php echo wp_kses_post( self::dependency_label( function_exists( 'tutor' ) || defined( 'TUTOR_VERSION' ) ) ); ?></li>
                    </ul>
                </div>

                <div class="lcw-card">
                    <h2>Product Setup</h2>
                    <p>Edit a WooCommerce product and open the <strong>Lesson Credits</strong> product data tab.</p>
                    <p><strong>Standard Product:</strong> ignored by the credit wallet.</p>
                    <p><strong>Credit Pack:</strong> grants credits when purchased.</p>
                    <p><strong>Redeemable Lesson:</strong> uses credits when booked.</p>
                    <p>The product tab now prevents a product from being both a pack and a redeemable lesson.</p>
                </div>

                <div class="lcw-card">
                    <h2>Current Build</h2>
                    <p><strong>Version:</strong> <?php echo esc_html( LCW_VERSION ); ?></p>
                    <p>This build includes product fields, manual credits, Credit Pack granting, checkout redemption, refund notes, ledger, Tutor dashboard display, clearer product-page messaging, and pack discount labels.</p>
                </div>
            </div>


            <div id="lcw-changelog" class="lcw-card lcw-card--changelog">
                <h2>Changelog</h2>
                <h3>Version 1.0.4</h3>
                <ul>
                    <li>Removed the plugin website row link from the plugin list.</li>
                    <li>Added GPLv2-or-later licence details.</li>
                    <li>Added a WordPress-style readme file for GitHub or future plugin directory use.</li>
                </ul>

                <h3>Version 1.0.3</h3>
                <ul>
                    <li>Added plugin list Settings link.</li>
                    <li>Added View details link to the admin changelog.</li>
                    <li>Added Baxtersweb author URL in plugin details.</li>
                </ul>

                <h3>Version 1.0.2</h3>
                <ul>
                    <li>Improved product page Pay with Credits wording.</li>
                    <li>Removed bold styling from product page credit messaging and pack links.</li>
                    <li>Hid duration text inside product credit price display.</li>
                    <li>Reduced visual size of Save percentage labels.</li>
                </ul>

                <h3>Version 1.0.1</h3>
                <ul class="lcw-checklist">
                    <li>Improved lesson product-page wording so Lesson Credits feel optional, not required.</li>
                    <li>Added inline Available Credit Packs with automatic Save percentage labels from regular/sale prices.</li>
                    <li>Added Credit Pack product-page information showing included 1 Hour Sessions and Lesson Credits.</li>
                    <li>Capitalised Lesson Credit / Lesson Credits consistently in customer-facing messaging.</li>
                </ul>
                <h3>Version 1.0.0</h3>
                <ul class="lcw-checklist">
                    <li>Initial stable release with Credit Packs, Redeemable Lessons, Tutor LMS dashboard card, credit ledger, manual adjustments, offline/EFT support, refund guidance, and WooCommerce order integration.</li>
                </ul>
            </div>

            <div class="lcw-card lcw-card--policy">
                <h2>Refund Policy / Admin Guidance</h2>
                <p><strong>Recommended rule:</strong> full refunds only. Do not offer partial credit refunds.</p>
                <p>If a lesson was paid using credits and the order is cancelled or fully refunded, the plugin can return those credits once.</p>
                <p>If a credit pack is refunded after some of its credits have already been spent, the plugin will not automatically force a negative balance. It will add a review note to the ledger and the order so an admin can decide what to do manually.</p>
                <p>For client support, use the ledger below to explain exactly when credits were granted, redeemed, returned, or flagged for review.</p><p><strong>Offline/EFT workflow:</strong> if a parent pays directly by bank transfer, an admin may switch to the customer, place the order with the admin-only EFT method, and only mark the order as completed once payment is confirmed. Credit packs grant credits when the order reaches processing or completed.</p>
            </div>

            <div class="lcw-card">
                <h2>Assign or Adjust Credits</h2>
                <p>Use this for offline purchases, corrections, or testing. You do not need to know the user ID.</p>
                <form method="post" class="lcw-form">
                    <?php wp_nonce_field( 'lcw_manual_adjustment' ); ?>
                    <input type="hidden" name="lcw_manual_adjustment" value="1">

                    <p>
                        <label for="lcw_user_id"><strong>User</strong></label><br>
                        <select id="lcw_user_id" name="lcw_user_id" class="wc-customer-search" data-placeholder="Search by name or email" data-allow_clear="true" style="width:420px" required>
                            <?php self::render_user_option( $selected_user ); ?>
                        </select>
                    </p>

                    <p>
                        <label for="lcw_amount"><strong>Credit Change</strong></label><br>
                        <input id="lcw_amount" type="number" name="lcw_amount" step="1" required placeholder="Example: 4 or -1">
                        <span class="description">Use positive numbers to add credits, negative numbers to subtract credits.</span>
                    </p>

                    <p>
                        <label for="lcw_expiry_date"><strong>Expiry Date</strong></label><br>
                        <input id="lcw_expiry_date" type="date" name="lcw_expiry_date">
                        <span class="description">Optional. Leave empty for no expiry.</span>
                    </p>

                    <p>
                        <label for="lcw_note"><strong>Reason / Note</strong></label><br>
                        <textarea id="lcw_note" name="lcw_note" rows="3" class="large-text" placeholder="Example: Purchased offline 4 lesson pack"></textarea>
                    </p>

                    <p><button class="button button-primary">Apply Credit Change</button></p>
                </form>
            </div>

            <div class="lcw-card">
                <h2>Users With Credits</h2>
                <table class="widefat striped">
                    <thead><tr><th>User</th><th>Email</th><th>Available Credits</th><th>Next Expiry</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ( $users ) : foreach ( $users as $user ) : ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td><strong><?php echo esc_html( $user->balance ); ?></strong></td>
                                <td><?php echo esc_html( LCW_Ledger::get_next_expiry( $user->ID ) ?: 'No expiry found' ); ?></td>
                                <td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'lesson-credit-wallet', 'lcw_user' => $user->ID ), admin_url( 'admin.php' ) ) ); ?>">Adjust / View</a></td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="5">No users with credits yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="lcw-card">
                <h2>Recent Credit Ledger</h2>
                <table class="widefat striped">
                    <thead><tr><th>Date</th><th>User</th><th>Change</th><th>Balance After</th><th>Type</th><th>Expiry</th><th>Note</th></tr></thead>
                    <tbody>
                        <?php if ( $entries ) : foreach ( $entries as $entry ) : $user = get_userdata( $entry->user_id ); ?>
                            <tr>
                                <td><?php echo esc_html( $entry->created_at ); ?></td>
                                <td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : '#' . esc_html( $entry->user_id ); ?></td>
                                <td class="<?php echo (int) $entry->change_amount >= 0 ? 'lcw-positive' : 'lcw-negative'; ?>"><?php echo esc_html( $entry->change_amount ); ?></td>
                                <td><?php echo esc_html( $entry->balance_after ); ?></td>
                                <td><?php echo esc_html( $entry->entry_type ); ?></td>
                                <td><?php echo esc_html( $entry->expiry_date ?: '—' ); ?></td>
                                <td><?php echo esc_html( $entry->note ); ?></td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="7">No ledger entries yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
