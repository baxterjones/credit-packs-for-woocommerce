<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Tutor {
    private static $rendered = false;

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

        // Tutor LMS dashboard hook support varies a bit by template/version.
        // Register on a few safe positions, then render only once.
        add_action( 'tutor_dashboard/before/content', array( __CLASS__, 'dashboard_summary_block' ), 20 );
        add_action( 'tutor_dashboard/before/wrap', array( __CLASS__, 'dashboard_summary_block' ), 20 );
    }

    public static function assets() {
        wp_enqueue_style( 'bxtr-cp-frontend', BXTR_CP_PLUGIN_URL . 'assets/css/frontend.css', array(), BXTR_CP_VERSION );
    }

    public static function dashboard_summary_block( $hidden = false, $fallback_id = 'bxtr-cp-dashboard-card' ) {
        if ( self::$rendered && ! $hidden && 'bxtr-cp-dashboard-card-shortcode' !== $fallback_id ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id = get_current_user_id();
        $balance = BXTR_CP_Ledger::get_balance( $user_id );
        $next_expiry = BXTR_CP_Ledger::get_next_expiry( $user_id );
        $recent = BXTR_CP_Ledger::get_recent_entries( 4, $user_id );
        $packs = self::get_credit_pack_products( 3 );

        if ( ! $hidden && 'bxtr-cp-dashboard-card-shortcode' !== $fallback_id ) {
            self::$rendered = true;
        }
        ?>
        <?php
        $data = array(
            'credit_balance' => $balance,
            'credit_label' => BXTR_CP_Settings::credit_label( $balance ),
            'credit_label_plural' => BXTR_CP_Settings::get( 'credit_label_plural' ),
        );
        ?>
        <div class="bxtr-cp-dashboard-card" id="<?php echo esc_attr( $fallback_id ); ?>" style="<?php echo esc_attr( BXTR_CP_Settings::frontend_style_attr() . ( $hidden ? 'display:none;' : '' ) ); ?>">
            <div class="bxtr-cp-dashboard-card__main">
                <div class="bxtr-cp-dashboard-card__label"><?php echo BXTR_CP_Settings::safe_icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'dashboard_title' ), $data ) ); ?></span></div>
                <div class="bxtr-cp-dashboard-card__balance-row">
                    <span class="bxtr-cp-dashboard-card__balance-text"><?php echo esc_html( BXTR_CP_Settings::replace_tokens( BXTR_CP_Settings::get( 'dashboard_balance' ), $data ) ); ?></span>
                </div>
                <div class="bxtr-cp-dashboard-card__meta">
                    <strong>Next expiry:</strong> <?php echo esc_html( $next_expiry ?: 'No expiry date' ); ?>
                </div>
                <?php if ( $packs ) : ?>
                    <div class="bxtr-cp-pack-links">
                        <strong>Need more <?php echo esc_html( BXTR_CP_Settings::get( 'credit_label_plural' ) ); ?>?</strong>
                        <?php foreach ( $packs as $pack ) : ?>
                            <a class="bxtr-cp-pack-link" href="<?php echo esc_url( get_permalink( $pack->ID ) ); ?>"><?php echo esc_html( get_the_title( $pack->ID ) ); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bxtr-cp-dashboard-card__activity">
                <strong>Recent activity</strong>
                <?php if ( $recent ) : ?>
                    <ul>
                        <?php foreach ( $recent as $entry ) : ?>
                            <li>
                                <span class="<?php echo (int) $entry->change_amount >= 0 ? 'bxtr-cp-positive' : 'bxtr-cp-negative'; ?>">
                                    <?php echo esc_html( (int) $entry->change_amount > 0 ? '+' . (int) $entry->change_amount : (int) $entry->change_amount ); ?>
                                </span>
                                <span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $entry->entry_type ) ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p>No credit activity yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function get_credit_pack_products( $limit = 3 ) {
        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $limit ),
            'fields'         => 'all',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small product lookup for dashboard pack links.
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

        return $query->posts;
    }

}