<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_manual_adjustment' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_settings_save' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_product_settings_save' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( BXTR_CP_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
        add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
    }

    public static function assets( $hook ) {
        if ( false === strpos( $hook, 'credit-packs-for-woocommerce' ) ) return;

        wp_enqueue_style( 'bxtr-cp-admin', BXTR_CP_PLUGIN_URL . 'assets/css/admin.css', array(), BXTR_CP_VERSION );
        wp_enqueue_script( 'bxtr-cp-admin', BXTR_CP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BXTR_CP_VERSION, true );

        if ( class_exists( 'WooCommerce' ) ) {
            wp_enqueue_script( 'wc-enhanced-select' );
            wp_enqueue_style( 'woocommerce_admin_styles' );
        }
    }

    public static function plugin_action_links( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=credit-packs-for-woocommerce' ) ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public static function plugin_row_meta( $links, $file ) {
        if ( plugin_basename( BXTR_CP_PLUGIN_FILE ) !== $file ) {
            return $links;
        }

        $links[] = '<a href="https://baxtersweb.com/credit-packs-woocommerce-docs" target="_blank" rel="noopener">Docs</a>';
        return $links;
    }

    public static function admin_menu() {
        add_menu_page(
            'Credit Packs for WooCommerce',
            'Credit Packs',
            'manage_options',
            'credit-packs-for-woocommerce',
            array( __CLASS__, 'render_page' ),
            'dashicons-tickets-alt',
            56
        );
    }

    private static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    private static function active_tab() {
        $tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        $tab = $tab ? sanitize_key( $tab ) : 'overview';
        return in_array( $tab, array( 'overview', 'products', 'customers', 'display', 'messages', 'shortcodes' ), true ) ? $tab : 'overview';
    }

    public static function handle_manual_adjustment() {
        if ( empty( $_POST['bxtr_cp_manual_adjustment'] ) ) return;
        if ( ! self::can_manage() ) return;
        check_admin_referer( 'bxtr_cp_manual_adjustment' );

        $user_id = isset( $_POST['bxtr_cp_user_id'] ) ? absint( wp_unslash( $_POST['bxtr_cp_user_id'] ) ) : 0;
        $amount  = isset( $_POST['bxtr_cp_amount'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['bxtr_cp_amount'] ) ) : 0;
        $note    = isset( $_POST['bxtr_cp_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bxtr_cp_note'] ) ) : '';
        $expiry  = isset( $_POST['bxtr_cp_expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bxtr_cp_expiry_date'] ) ) : '';

        if ( $user_id && 0 !== $amount ) {
            BXTR_CP_Ledger::add_entry( array(
                'user_id'       => $user_id,
                'change_amount' => $amount,
                'entry_type'    => 'manual',
                'expiry_date'   => $expiry ?: null,
                'note'          => $note ?: 'Manual credit adjustment.',
            ) );

            wp_safe_redirect( add_query_arg( array(
                'page'            => 'credit-packs-for-woocommerce',
                'tab'             => 'customers',
                'bxtr_cp_updated' => 1,
                'bxtr_cp_user'    => $user_id,
            ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    public static function handle_settings_save() {
        if ( empty( $_POST['bxtr_cp_save_settings'] ) ) return;
        if ( ! self::can_manage() ) return;
        check_admin_referer( 'bxtr_cp_save_settings' );

        $raw_settings = isset( $_POST['bxtr_cp_settings'] ) ? map_deep( (array) wp_unslash( $_POST['bxtr_cp_settings'] ), 'sanitize_text_field' ) : array();
        BXTR_CP_Settings::save_from_post( $raw_settings );
        $tab = isset( $_POST['bxtr_cp_tab'] ) ? sanitize_key( wp_unslash( $_POST['bxtr_cp_tab'] ) ) : 'display';
        if ( ! in_array( $tab, array( 'display', 'messages' ), true ) ) {
            $tab = 'display';
        }

        wp_safe_redirect( add_query_arg( array(
            'page'                   => 'credit-packs-for-woocommerce',
            'tab'                    => $tab,
            'bxtr_cp_settings_saved' => 1,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_product_settings_save() {
        if ( empty( $_POST['bxtr_cp_save_product_settings'] ) ) return;
        if ( ! self::can_manage() ) return;
        check_admin_referer( 'bxtr_cp_save_product_settings' );

        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_safe_redirect( add_query_arg( array(
                'page' => 'credit-packs-for-woocommerce',
                'tab'  => 'products',
                'bxtr_cp_woo_missing' => 1,
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $products = isset( $_POST['bxtr_cp_products'] ) ? map_deep( (array) wp_unslash( $_POST['bxtr_cp_products'] ), 'sanitize_text_field' ) : array();

        foreach ( $products as $product_id => $values ) {
            $product_id = absint( $product_id );
            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;

            $type = isset( $values['type'] ) ? sanitize_key( $values['type'] ) : 'standard';
            if ( ! in_array( $type, array( 'standard', 'pack', 'product' ), true ) ) {
                $type = 'standard';
            }

            $required = isset( $values['required'] ) ? absint( $values['required'] ) : 0;
            $granted  = isset( $values['granted'] ) ? absint( $values['granted'] ) : 0;
            $expiry   = isset( $values['expiry'] ) ? absint( $values['expiry'] ) : 0;

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
            $product->save();
        }

        wp_safe_redirect( add_query_arg( array(
            'page'                   => 'credit-packs-for-woocommerce',
            'tab'                    => 'products',
            'bxtr_cp_products_saved' => 1,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function dependency_label( $ok, $good = 'Active', $bad = 'Not detected' ) {
        return $ok ? '<span class="bxtr-status bxtr-status--ok">' . esc_html( $good ) . '</span>' : '<span class="bxtr-status bxtr-status--warn">' . esc_html( $bad ) . '</span>';
    }

    private static function render_user_option( $user_id ) {
        if ( ! $user_id ) return;
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        echo '<option value="' . esc_attr( $user_id ) . '" selected="selected">' . esc_html( $user->display_name . ' (' . $user->user_email . ')' ) . '</option>';
    }

    private static function tabs() {
        return array(
            'overview'   => 'Overview',
            'products'   => 'Products',
            'customers'  => 'Customers & Ledger',
            'display'    => 'Labels, Style & Icons',
            'messages'   => 'Message Templates',
            'shortcodes' => 'Shortcodes',
        );
    }

    private static function render_tabs( $active ) {
        echo '<nav class="nav-tab-wrapper bxtr-cp-tabs">';
        foreach ( self::tabs() as $tab => $label ) {
            $class = $active === $tab ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'credit-packs-for-woocommerce', 'tab' => $tab ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }

    public static function render_page() {
        if ( ! self::can_manage() ) return;

        $active = self::active_tab();
        ?>
        <div class="wrap bxtr-cp-admin">
            <div class="bxtr-cp-title-row">
                <div class="bxtr-cp-title-main">
                    <h1>Credit Packs for WooCommerce</h1>
                    <p class="bxtr-cp-intro">Sell prepaid credit packs and let customers redeem credits on selected WooCommerce products.</p>
                </div>
                <div class="bxtr-admin-links" aria-label="Credit Packs links">
                    <a href="https://baxtersweb.com/credit-packs-woocommerce-docs" target="_blank" rel="noopener">Docs</a>
                    <a href="https://baxtersweb.com/credit-packs-woocommerce-demo" target="_blank" rel="noopener">Demo</a>
                    <span class="bxtr-version">Version <?php echo esc_html( BXTR_CP_VERSION ); ?></span>
                </div>
            </div>

            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="notice notice-warning"><p><strong><?php esc_html_e( 'WooCommerce is required.', 'credit-packs-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Install and activate WooCommerce to use credit packs, redeemable products, checkout redemption, and product settings.', 'credit-packs-for-woocommerce' ); ?></p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['bxtr_cp_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p>Credit balance updated.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['bxtr_cp_settings_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p>Credit Pack settings saved.</p></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['bxtr_cp_products_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                <div class="notice notice-success is-dismissible"><p>Product credit settings saved.</p></div>
            <?php endif; ?>

            <?php self::render_tabs( $active ); ?>

            <?php if ( in_array( $active, array( 'display', 'messages' ), true ) ) : ?>
                <div class="bxtr-cp-admin-layout">
                    <main class="bxtr-cp-admin-main">
                        <?php self::render_tab( $active ); ?>
                    </main>
                    <aside class="bxtr-cp-admin-side">
                        <?php self::render_preview_card(); ?>
                    </aside>
                </div>
            <?php else : ?>
                <?php self::render_tab( $active ); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_tab( $active ) {
        switch ( $active ) {
            case 'products':
                self::render_products_tab();
                break;
            case 'customers':
                self::render_customers_tab();
                break;
            case 'display':
                self::render_display_tab();
                break;
            case 'messages':
                self::render_messages_tab();
                break;
            case 'shortcodes':
                self::render_shortcodes_tab();
                break;
            case 'overview':
            default:
                self::render_overview_tab();
                break;
        }
    }

    private static function render_overview_tab() {
        ?>
        <div class="bxtr-grid bxtr-grid--two">
            <div class="bxtr-card">
                <h2>Requirements</h2>
                <ul class="bxtr-cp-checklist">
                    <li><strong>WooCommerce:</strong> <?php echo wp_kses_post( self::dependency_label( class_exists( 'WooCommerce' ) ) ); ?></li>
                </ul>
                <p>WooCommerce is the only required plugin. Credit packs are purchased as WooCommerce products and redeemed during WooCommerce checkout.</p>
            </div>

            <div class="bxtr-card">
                <h2>Optional Support</h2>
                <div class="bxtr-cp-optional-support">
                    <h3>Tutor LMS <?php echo wp_kses_post( self::dependency_label( function_exists( 'tutor' ) || defined( 'TUTOR_VERSION' ), 'Supported', 'Optional' ) ); ?></h3>
                    <p class="description">Tutor Description: display customer credit balances inside Tutor LMS dashboard areas where supported, or place the shortcode manually in your preferred dashboard location.</p>

                    <h3><a href="https://wordpress.org/plugins/acf-views/" target="_blank" rel="noopener">Advanced Views</a> <?php echo wp_kses_post( self::dependency_label( defined( 'ACF_VIEWS_VERSION' ) || class_exists( 'ACFViews\\Plugin' ), 'Supported', 'Optional' ) ); ?></h3>
                    <p class="description">AV Description: use Credit Packs shortcodes inside Advanced Views Layouts to display customer balances and product credit options.</p>
                </div>
            </div>

            <div class="bxtr-card bxtr-card--how-it-works">
                <h2>How Credit Packs Work</h2>
                <p>Any WooCommerce product can be made into a credit pack or redeemable product type.</p>
                <p class="bxtr-cp-flow-text">Create a product <span aria-hidden="true">→</span> Set Credit Type <span aria-hidden="true">→</span> Customer buys the pack <span aria-hidden="true">→</span> Credits are added <span aria-hidden="true">→</span> Customer redeems credits later</p>

                <h3>Credit Types Explained</h3>
                <p><strong>Standard Product:</strong> a normal WooCommerce product with no credit pack behaviour.</p>
                <p><strong>Credit Pack:</strong> a product customers buy to receive credits.</p>
                <p><strong>Redeemable Product:</strong> a product customers can purchase using credits from their balance.</p>
            </div>

            <div class="bxtr-card">
                <h2>Suggested Setup Order</h2>
                <ol class="bxtr-cp-steps">
                    <li>Set your labels, colours, and icon style.</li>
                    <li>Review the customer-facing message templates.</li>
                    <li>Use the Products tab to mark products as Credit Packs or Redeemable Products.</li>
                    <li>Use the Customers & Ledger tab for manual corrections and support.</li>
                </ol>
            </div>

            <div class="bxtr-card bxtr-card--notice">
                <h2>Refund Policy / Admin Guidance</h2>
                <p><strong>Recommended rule:</strong> full refunds only. Avoid partial credit refunds unless an admin reviews the ledger manually.</p>
                <p>If a product was paid using credits and the order is cancelled or fully refunded, the plugin can return those credits once.</p>
                <p>If a credit pack is refunded after some credits have already been spent, the plugin will not force a negative balance. It adds a review note to the ledger and order so an admin can decide what to do.</p>
            </div>

            <div class="bxtr-card">
                <h2>Quick Links</h2>
                <p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'credit-packs-for-woocommerce', 'tab' => 'products' ), admin_url( 'admin.php' ) ) ); ?>">Configure Product Credit Type</a></p>
                <p><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'credit-packs-for-woocommerce', 'tab' => 'display' ), admin_url( 'admin.php' ) ) ); ?>">Customise Frontend Styles</a></p>
            </div>
        </div>
        <?php
    }

    private static function render_products_tab() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="bxtr-card"><h2>Product Credit Settings</h2><p>WooCommerce must be active before product credit settings can be managed.</p></div>';
            return;
        }

        $products = wc_get_products( array( 'limit' => 150, 'status' => array( 'publish', 'draft', 'private' ), 'orderby' => 'title', 'order' => 'ASC' ) );
        ?>
        <div class="bxtr-card">
            <h2>Product Credit Settings</h2>
            <p>Bulk update only the credit pack settings. This avoids opening every WooCommerce product one by one.</p>
            <form method="post">
                <?php wp_nonce_field( 'bxtr_cp_save_product_settings' ); ?>
                <input type="hidden" name="bxtr_cp_save_product_settings" value="1">
                <table class="widefat striped bxtr-cp-products-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Credit Type</th>
                            <th>Credits Granted<br><span class="description">Only used for Credit Pack products.</span></th>
                            <th>Credits Required<br><span class="description">Only used for Redeemable Products.</span></th>
                            <th>Expiry Days<br><span class="description">Only used for Credit Pack products.</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( $products ) : foreach ( $products as $product ) :
                        $product_id = $product->get_id();
                        $type       = BXTR_CP_Products::get_credit_type( $product_id );
                        $granted    = BXTR_CP_Products::get_credits_granted( $product_id );
                        $required   = BXTR_CP_Products::get_credits_required( $product_id );
                        $expiry     = BXTR_CP_Products::get_expiry_days( $product_id );
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $product->get_name() ); ?></strong><br><span class="description">#<?php echo esc_html( $product_id ); ?></span></td>
                            <td><?php echo wp_kses_post( $product->get_price_html() ?: '&mdash;' ); ?></td>
                            <td>
                                <select class="bxtr-cp-product-type" name="bxtr_cp_products[<?php echo esc_attr( $product_id ); ?>][type]">
                                    <option value="standard" <?php selected( $type, 'standard' ); ?>>Standard Product</option>
                                    <option value="pack" <?php selected( $type, 'pack' ); ?>>Credit Pack</option>
                                    <option value="product" <?php selected( $type, 'product' ); ?>>Redeemable Product</option>
                                </select>
                            </td>
                            <td data-credit-field="granted"><input type="number" min="0" step="1" name="bxtr_cp_products[<?php echo esc_attr( $product_id ); ?>][granted]" value="<?php echo esc_attr( $granted ); ?>" class="small-text"><span class="description bxtr-cp-field-note">Saved only when type is Credit Pack.</span></td>
                            <td data-credit-field="required"><input type="number" min="0" step="1" name="bxtr_cp_products[<?php echo esc_attr( $product_id ); ?>][required]" value="<?php echo esc_attr( $required ); ?>" class="small-text"><span class="description bxtr-cp-field-note">Saved only when type is Redeemable Product.</span></td>
                            <td data-credit-field="expiry"><input type="number" min="0" step="1" name="bxtr_cp_products[<?php echo esc_attr( $product_id ); ?>][expiry]" value="<?php echo esc_attr( $expiry ); ?>" class="small-text"><span class="description bxtr-cp-field-note">Saved only when type is Credit Pack.</span></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="6">No WooCommerce products found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <p><button class="button button-primary">Save Product Credit Settings</button></p>
            </form>
        </div>
        <?php
    }

    private static function render_customers_tab() {
        $users         = BXTR_CP_Ledger::get_users_with_balances( 150 );
        $ledger_user_q  = isset( $_GET['bxtr_cp_ledger_user'] ) ? sanitize_text_field( wp_unslash( $_GET['bxtr_cp_ledger_user'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ledger_user    = BXTR_CP_Ledger::resolve_user_search( $ledger_user_q );
        $ledger_type    = isset( $_GET['bxtr_cp_ledger_type'] ) ? sanitize_key( wp_unslash( $_GET['bxtr_cp_ledger_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ledger_search  = isset( $_GET['bxtr_cp_ledger_search'] ) ? sanitize_text_field( wp_unslash( $_GET['bxtr_cp_ledger_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $entries       = BXTR_CP_Ledger::search_entries( array( 'limit' => 100, 'user_id' => $ledger_user, 'user_search' => $ledger_user_q, 'type' => $ledger_type, 'search' => $ledger_search ) );
        $selected_user = isset( $_GET['bxtr_cp_user'] ) ? absint( wp_unslash( $_GET['bxtr_cp_user'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="bxtr-cp-customers-layout">
            <div class="bxtr-cp-customers-main">
                <div class="bxtr-card bxtr-card--ledger">
                    <h2>Credit Ledger</h2>
                    <p>Search the ledger when a customer asks where credits came from, where they were used, or why their balance changed.</p>
                    <form method="get" class="bxtr-cp-ledger-filters">
                        <input type="hidden" name="page" value="credit-packs-for-woocommerce">
                        <input type="hidden" name="tab" value="customers">
                        <label><strong>Customer</strong><br><input type="search" name="bxtr_cp_ledger_user" value="<?php echo esc_attr( $ledger_user_q ); ?>" placeholder="Name, email, or user ID"></label>
                        <label><strong>Type</strong><br><select name="bxtr_cp_ledger_type"><option value="">All types</option><?php foreach ( BXTR_CP_Ledger::entry_types() as $entry_type ) : ?><option value="<?php echo esc_attr( $entry_type ); ?>" <?php selected( $ledger_type, $entry_type ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry_type ) ) ); ?></option><?php endforeach; ?></select></label>
                        <label><strong>Search note</strong><br><input type="search" name="bxtr_cp_ledger_search" value="<?php echo esc_attr( $ledger_search ); ?>" placeholder="Order number, product, note"></label>
                        <button class="button">Filter Ledger</button>
                        <a class="button bxtr-cp-clear-button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'credit-packs-for-woocommerce', 'tab' => 'customers' ), admin_url( 'admin.php' ) ) ); ?>">Clear</a>
                    </form>
                    <table class="widefat striped">
                        <thead><tr><th>Date</th><th>User</th><th>Change</th><th>Balance After</th><th>Type</th><th>Expiry</th><th>Note</th></tr></thead>
                        <tbody>
                            <?php if ( $entries ) : foreach ( $entries as $entry ) : $user = get_userdata( $entry->user_id ); ?>
                                <tr>
                                    <td><?php echo esc_html( $entry->created_at ); ?></td>
                                    <td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : '#' . esc_html( $entry->user_id ); ?></td>
                                    <td class="<?php echo (int) $entry->change_amount >= 0 ? 'bxtr-cp-positive' : 'bxtr-cp-negative'; ?>"><?php echo esc_html( $entry->change_amount ); ?></td>
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

                <div class="bxtr-card">
                    <h2>Users With Credits</h2>
                    <p>Use this as a quick balance list. For history, filter the ledger above by user.</p>
                    <table class="widefat striped">
                        <thead><tr><th>User</th><th>Email</th><th>Available Credits</th><th>Next Expiry</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if ( $users ) : foreach ( $users as $user ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $user->display_name ); ?></td>
                                    <td><?php echo esc_html( $user->user_email ); ?></td>
                                    <td><strong><?php echo esc_html( $user->balance ); ?></strong></td>
                                    <td><?php echo esc_html( BXTR_CP_Ledger::get_next_expiry( $user->ID ) ?: 'No expiry found' ); ?></td>
                                    <td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'credit-packs-for-woocommerce', 'tab' => 'customers', 'bxtr_cp_user' => $user->ID, 'bxtr_cp_ledger_user' => $user->ID ), admin_url( 'admin.php' ) ) ); ?>">Adjust / View</a></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="5">No users with credits yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bxtr-cp-customers-side">
            <div class="bxtr-card">
                <h2>Adjust Credits</h2>
                <p>Use this for offline purchases, corrections, goodwill credits, or testing.</p>
                <form method="post" class="bxtr-cp-form">
                    <?php wp_nonce_field( 'bxtr_cp_manual_adjustment' ); ?>
                    <input type="hidden" name="bxtr_cp_manual_adjustment" value="1">

                    <p>
                        <label for="bxtr_cp_user_id"><strong>User</strong></label><br>
                        <select id="bxtr_cp_user_id" name="bxtr_cp_user_id" class="wc-customer-search" data-placeholder="Search by name or email" data-allow_clear="true" style="width:420px" required>
                            <?php self::render_user_option( $selected_user ); ?>
                        </select>
                    </p>

                    <p>
                        <label for="bxtr_cp_amount"><strong>Credit Change</strong></label><br>
                        <input id="bxtr_cp_amount" type="number" name="bxtr_cp_amount" step="1" required placeholder="Example: 4 or -1">
                        <span class="description">Use positive numbers to add credits and negative numbers to subtract credits.</span>
                    </p>

                    <p>
                        <label for="bxtr_cp_expiry_date"><strong>Expiry Date</strong></label><br>
                        <input id="bxtr_cp_expiry_date" type="date" name="bxtr_cp_expiry_date">
                        <span class="description">Optional. Leave empty for no expiry.</span>
                    </p>

                    <p>
                        <label for="bxtr_cp_note"><strong>Reason / Note</strong></label><br>
                        <textarea id="bxtr_cp_note" name="bxtr_cp_note" rows="3" class="large-text" placeholder="Example: Purchased offline 4 credit pack"></textarea>
                    </p>

                    <p><button class="button button-primary">Apply Credit Change</button></p>
                </form>
            </div>

            <div class="bxtr-card bxtr-card--notice">
                <h2>Support Notes</h2>
                <p>The ledger is the source of truth. Use it when a customer asks why their balance changed.</p>
                <p>For offline/EFT workflows, add credits only once payment is confirmed, or complete the WooCommerce order so the pack grants credits normally.</p>
                <p>For refunds, check the ledger before manually subtracting credits. Avoid forcing a negative balance without reviewing the customer history.</p>
            </div>
        </div>
        <?php
    }

    private static function render_display_tab() {
        $settings = BXTR_CP_Settings::get_all();
        ?>
        <div class="bxtr-card bxtr-cp-card--settings">
            <h2>Labels, Style & Icons</h2>
            <p>Control how the frontend credit cards look and how credits are named across the customer experience.</p>
            <form method="post" class="bxtr-cp-settings-form" data-bxtr-cp-preview-form>
                <?php wp_nonce_field( 'bxtr_cp_save_settings' ); ?>
                <input type="hidden" name="bxtr_cp_save_settings" value="1">
                <input type="hidden" name="bxtr_cp_tab" value="display">

                <h3>Labels</h3>
                <div class="bxtr-grid bxtr-grid--two bxtr-grid--compact">
                    <p><label><strong>Credit label singular</strong><br><input type="text" name="bxtr_cp_settings[credit_label_singular]" value="<?php echo esc_attr( $settings['credit_label_singular'] ); ?>" class="regular-text" data-preview="credit_label"></label><span class="description">Used when exactly one credit is shown, for example “1 Credit”.</span></p>
                    <p><label><strong>Credit label plural</strong><br><input type="text" name="bxtr_cp_settings[credit_label_plural]" value="<?php echo esc_attr( $settings['credit_label_plural'] ); ?>" class="regular-text" data-preview="credit_label_plural"></label><span class="description">Used for balances and multiple credits, for example “8 Credits”.</span></p>
                    <p><label><strong>Pack label</strong><br><input type="text" name="bxtr_cp_settings[pack_label]" value="<?php echo esc_attr( $settings['pack_label'] ); ?>" class="regular-text" data-preview="pack_label"></label><span class="description">Used in headings such as “Your Credit Pack”.</span></p>
                </div>

                <h3>Style</h3>
                <p><label><strong>Style preset</strong><br>
                    <select name="bxtr_cp_settings[style_preset]" data-bxtr-cp-style-preset>
                        <option value="gold" <?php selected( $settings['style_preset'], 'gold' ); ?>>Gold</option>
                        <option value="silver" <?php selected( $settings['style_preset'], 'silver' ); ?>>Silver</option>
                        <option value="bronze" <?php selected( $settings['style_preset'], 'bronze' ); ?>>Bronze</option>
                        <option value="minimal" <?php selected( $settings['style_preset'], 'minimal' ); ?>>Minimal</option>
                        <option value="custom" <?php selected( $settings['style_preset'], 'custom' ); ?>>Custom</option>
                    </select>
                </label><span class="description">Gold, Silver, and Bronze are polished presets. Minimal keeps the card quieter. Custom lets you control the colours below.</span></p>

                <div class="bxtr-grid bxtr-grid--two bxtr-grid--compact">
                    <p><label><strong>Accent colour</strong><br><input type="text" name="bxtr_cp_settings[accent_colour]" value="<?php echo esc_attr( $settings['accent_colour'] ); ?>" class="regular-text" data-preview-style="accent"></label><span class="description">Used for icon colour, links, and highlighted credit text.</span></p>
                    <p><label><strong>Background colour</strong><br><input type="text" name="bxtr_cp_settings[background_colour]" value="<?php echo esc_attr( $settings['background_colour'] ); ?>" class="regular-text" data-preview-style="background"></label><span class="description">The main card background colour shown on product and dashboard cards.</span></p>
                    <p><label><strong>Border colour</strong><br><input type="text" name="bxtr_cp_settings[border_colour]" value="<?php echo esc_attr( $settings['border_colour'] ); ?>" class="regular-text" data-preview-style="border"></label><span class="description">The outline around frontend credit cards.</span></p>
                    <p><label><strong>Text colour</strong><br><input type="text" name="bxtr_cp_settings[text_colour]" value="<?php echo esc_attr( $settings['text_colour'] ); ?>" class="regular-text" data-preview-style="text"></label><span class="description">Primary text colour inside credit pack cards.</span></p>
                    <p class="bxtr-cp-field-with-space bxtr-cp-field-border-radius"><label for="bxtr_cp_border_radius"><strong>Border radius</strong></label><br><input id="bxtr_cp_border_radius" type="number" min="0" max="40" name="bxtr_cp_settings[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" class="small-text" data-preview-style="radius"> px<span class="description">Controls how rounded the frontend cards and icon background appear.</span></p>
                    <p><label for="bxtr_cp_font_size"><strong>Card font size</strong></label><br><input id="bxtr_cp_font_size" type="number" min="11" max="22" name="bxtr_cp_settings[font_size]" value="<?php echo esc_attr( $settings['font_size'] ); ?>" class="small-text" data-preview-style="font_size"> px<span class="description">Controls the base font size inside frontend credit pack cards. The default is 14px.</span></p>
                    <p><label for="bxtr_cp_label_font_size"><strong>Label font size</strong></label><br><input id="bxtr_cp_label_font_size" type="number" min="11" max="22" name="bxtr_cp_settings[label_font_size]" value="<?php echo esc_attr( $settings['label_font_size'] ); ?>" class="small-text" data-preview-style="label_font_size"> px<span class="description">Controls the title/label font size inside frontend credit pack cards. The default is 14px.</span></p>
                </div>

                <h3>Icon</h3>
                <p><label><input type="radio" name="bxtr_cp_settings[icon_mode]" value="builtin" <?php checked( $settings['icon_mode'], 'builtin' ); ?>> Built-in SVG icon</label><span class="description">Uses a lightweight icon included with the plugin.</span></p>
                <p><label><input type="radio" name="bxtr_cp_settings[icon_mode]" value="class" <?php checked( $settings['icon_mode'], 'class' ); ?>> Theme icon class</label><span class="description">Outputs your class on the icon element. The theme or custom icon set must load the icon CSS on the frontend. No style preview available.</span></p>
                <p><label><input type="radio" name="bxtr_cp_settings[icon_mode]" value="none" <?php checked( $settings['icon_mode'], 'none' ); ?>> No icon</label><span class="description">Removes the icon and related spacing from frontend cards.</span></p>
                <div class="bxtr-cp-icon-option bxtr-cp-icon-option--builtin">
                    <p><label><strong>Built-in icon</strong><br>
                        <select name="bxtr_cp_settings[icon_builtin]">
                            <option value="ticket" <?php selected( $settings['icon_builtin'], 'ticket' ); ?>>Ticket</option>
                            <option value="check" <?php selected( $settings['icon_builtin'], 'check' ); ?>>Check circle</option>
                            <option value="card" <?php selected( $settings['icon_builtin'], 'card' ); ?>>Card</option>
                            <option value="package" <?php selected( $settings['icon_builtin'], 'package' ); ?>>Package</option>
                        </select>
                    </label><span class="description">Choose which built-in SVG icon appears in the frontend credit cards.</span></p>
                </div>
                <div class="bxtr-cp-icon-option bxtr-cp-icon-option--class">
                    <p><label><strong>Theme icon class</strong><br><input type="text" name="bxtr_cp_settings[icon_class]" value="<?php echo esc_attr( $settings['icon_class'] ); ?>" class="regular-text" placeholder="icon-feather-package"></label><span class="description">Add only the CSS class name, for example <code>icon-feather-package</code>. Do not add dots, bullets, quotes, or HTML. Your theme or icon library must load the icon CSS.</span></p>
                </div>

                <h3>Uninstall Data</h3>
                <input type="hidden" name="bxtr_cp_settings[remove_data_on_uninstall]" value="no">
                <p><label><input type="checkbox" name="bxtr_cp_settings[remove_data_on_uninstall]" value="yes" <?php checked( $settings['remove_data_on_uninstall'], 'yes' ); ?>> <strong>Remove plugin data on uninstall</strong></label><span class="description">Permanently deletes Credit Packs settings, customer balances, product credit settings, and ledger data when the plugin is uninstalled. Leave this off on live sites.</span></p>

                <p><button class="button button-primary">Save Display Settings</button></p>
            </form>
        </div>
        <?php
    }

    private static function render_messages_tab() {
        $settings = BXTR_CP_Settings::get_all();
        ?>
        <div class="bxtr-card bxtr-cp-card--settings">
            <h2>Message Templates</h2>
            <p>Write the customer-facing wording once, then reuse it across products, checkout, and dashboard cards.</p>
            <form method="post" class="bxtr-cp-settings-form" data-bxtr-cp-preview-form>
                <?php wp_nonce_field( 'bxtr_cp_save_settings' ); ?>
                <input type="hidden" name="bxtr_cp_save_settings" value="1">
                <input type="hidden" name="bxtr_cp_tab" value="messages">

                <p><label><strong>Product notice title</strong><br><input type="text" name="bxtr_cp_settings[redeemable_title]" value="<?php echo esc_attr( $settings['redeemable_title'] ); ?>" class="large-text" data-preview="redeemable_title"></label></p>
                <p><label><strong>Product notice sentence</strong><br><input type="text" name="bxtr_cp_settings[redeemable_message]" value="<?php echo esc_attr( $settings['redeemable_message'] ); ?>" class="large-text" data-preview="redeemable_message"></label></p>
                <p><label><strong>Balance sentence</strong><br><input type="text" name="bxtr_cp_settings[balance_message]" value="<?php echo esc_attr( $settings['balance_message'] ); ?>" class="large-text"></label></p>
                <p><label><strong>Checkbox label</strong><br><input type="text" name="bxtr_cp_settings[checkbox_label]" value="<?php echo esc_attr( $settings['checkbox_label'] ); ?>" class="large-text"></label></p>
                <p><label><strong>Pack title</strong><br><input type="text" name="bxtr_cp_settings[pack_title]" value="<?php echo esc_attr( $settings['pack_title'] ); ?>" class="large-text"></label></p>
                <p><label><strong>Pack sentence</strong><br><input type="text" name="bxtr_cp_settings[pack_message]" value="<?php echo esc_attr( $settings['pack_message'] ); ?>" class="large-text"></label></p>
                <p><label><strong>Pack redeem sentence</strong><br><input type="text" name="bxtr_cp_settings[pack_redeem_message]" value="<?php echo esc_attr( $settings['pack_redeem_message'] ); ?>" class="large-text"></label></p>
                <p><label><strong>Dashboard title</strong><br><input type="text" name="bxtr_cp_settings[dashboard_title]" value="<?php echo esc_attr( $settings['dashboard_title'] ); ?>" class="large-text"></label></p>
                <p><label><strong>Dashboard balance</strong><br><input type="text" name="bxtr_cp_settings[dashboard_balance]" value="<?php echo esc_attr( $settings['dashboard_balance'] ); ?>" class="large-text"></label></p>
                <p class="description">Available variables: {credit_balance}, {credits_required}, {credits_granted}, {credit_label}, {credit_label_plural}, {pack_label}, {pack_label_plural}, {product_name}.</p>

                <p><button class="button button-primary">Save Message Templates</button></p>
            </form>
        </div>
        <?php
    }

    private static function render_preview_card() {
        $settings = BXTR_CP_Settings::get_all();
        $title = BXTR_CP_Settings::replace_tokens( $settings['redeemable_title'], array( 'credits_required' => 1, 'credit_balance' => 8, 'product_name' => 'Sample Product' ) );
        $message = BXTR_CP_Settings::replace_tokens( $settings['redeemable_message'], array( 'credits_required' => 1, 'credit_balance' => 8, 'product_name' => 'Sample Product' ) );
        ?>
        <div class="bxtr-card bxtr-cp-preview-wrap">
            <h2>Style Preview</h2>
            <div class="bxtr-cp-admin-preview" style="<?php echo esc_attr( BXTR_CP_Settings::frontend_style_attr() ); ?>" data-bxtr-cp-preview>
                <div class="bxtr-cp-product-credit-box__title"><span data-preview-output="redeemable_title"><?php echo esc_html( $title ); ?></span><span class="bxtr-cp-preview-icon-slot" data-bxtr-cp-preview-icon><?php echo BXTR_CP_Settings::safe_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></div>
                <p data-preview-output="redeemable_message"><?php echo esc_html( $message ); ?></p>
            </div>
            <p class="description">This preview shows the customer-facing card styling. It updates as you change labels, messages, colours, and icons.</p>
            <h3>Frontend CSS Classes</h3>
            <table class="widefat bxtr-cp-css-table">
                <thead><tr><th>Description</th><th>CSS class</th></tr></thead>
                <tbody>
                    <tr><td>Main product notice card.</td><td><code>.bxtr-cp-product-credit-box</code></td></tr>
                    <tr><td>Notice heading row and icon alignment.</td><td><code>.bxtr-cp-product-credit-box__title</code></td></tr>
                    <tr><td>Use credits checkbox label.</td><td><code>.bxtr-cp-use-credits-label</code></td></tr>
                    <tr><td>Customer dashboard balance card.</td><td><code>.bxtr-cp-dashboard-card</code></td></tr>
                    <tr><td>Built-in or theme icon wrapper.</td><td><code>.bxtr-cp-icon</code></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_shortcodes_tab() {
        ?>
        <div class="bxtr-card bxtr-card--shortcodes">
            <h2>Shortcodes</h2>
            <p>Use these shortcodes when you want to place Credit Packs output in a custom location, page builder layout, or <a href="https://wordpress.org/plugins/acf-views/" target="_blank" rel="noopener">Advanced Views</a> layout.</p>
            <table class="widefat bxtr-cp-css-table">
                <thead><tr><th>Description</th><th>Shortcode</th></tr></thead>
                <tbody>
                    <tr><td>Customer credit card for the logged-in user.</td><td><code>[bxtr_credit_packs]</code></td></tr>
                    <tr><td>Balance only for the logged-in user.</td><td><code>[bxtr_credit_packs type="balance"]</code></td></tr>
                    <tr><td>Product credit box for a specific WooCommerce product.</td><td><code>[bxtr_credit_packs type="product" product_id="123"]</code></td></tr>
                    <tr><td><a href="https://wordpress.org/plugins/acf-views/" target="_blank" rel="noopener">Advanced Views</a> product layout example.</td><td><code>[bxtr_credit_packs type="product" product_id="{{ _layout.object_id }}"]</code></td></tr>
                </tbody>
            </table>
            <p class="description">The product shortcode uses the same frontend styles and message templates as the automatic product card.</p>
        </div>
        <?php
    }

}
