<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Tutor {
    private static $rendered = false;

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

        // Tutor LMS dashboard hook support varies a bit by template/version.
        // Register on a few safe positions, then render only once.
        add_action( 'tutor_dashboard/before/content', array( __CLASS__, 'dashboard_summary_block' ), 20 );
        add_action( 'tutor_dashboard/before/wrap', array( __CLASS__, 'dashboard_summary_block' ), 20 );
        add_action( 'wp_footer', array( __CLASS__, 'move_dashboard_card_script' ), 30 );
    }

    public static function assets() {
        wp_enqueue_style( 'lcw-frontend', LCW_PLUGIN_URL . 'assets/css/frontend.css', array(), LCW_VERSION );
    }

    public static function dashboard_summary_block() {
        if ( self::$rendered ) return;
        if ( ! is_user_logged_in() ) return;

        $user_id = get_current_user_id();
        $balance = LCW_Ledger::get_balance( $user_id );
        $next_expiry = LCW_Ledger::get_next_expiry( $user_id );
        $recent = LCW_Ledger::get_recent_entries( 4, $user_id );
        $packs = self::get_credit_pack_products( 3 );

        self::$rendered = true;
        ?>
        <div class="lcw-dashboard-card" id="lcw-dashboard-card">
            <div class="lcw-dashboard-card__main">
                <div class="lcw-dashboard-card__label">Lesson Credit Wallet</div>
                <div class="lcw-dashboard-card__balance-row">
                    <span class="lcw-dashboard-card__balance"><?php echo esc_html( $balance ); ?></span>
                    <span class="lcw-dashboard-card__balance-text">available Lesson Credit<?php echo 1 === (int) $balance ? '' : 's'; ?></span>
                </div>
                <div class="lcw-dashboard-card__meta">
                    <strong>Next expiry:</strong> <?php echo esc_html( $next_expiry ?: 'No expiry date' ); ?>
                </div>
                <?php if ( $packs ) : ?>
                    <div class="lcw-pack-links">
                        <strong>Need more Lesson Credits?</strong>
                        <?php foreach ( $packs as $pack ) : ?>
                            <a class="lcw-pack-link" href="<?php echo esc_url( get_permalink( $pack->ID ) ); ?>"><?php echo esc_html( get_the_title( $pack->ID ) ); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lcw-dashboard-card__activity">
                <strong>Recent activity</strong>
                <?php if ( $recent ) : ?>
                    <ul>
                        <?php foreach ( $recent as $entry ) : ?>
                            <li>
                                <span class="<?php echo (int) $entry->change_amount >= 0 ? 'lcw-positive' : 'lcw-negative'; ?>">
                                    <?php echo esc_html( (int) $entry->change_amount > 0 ? '+' . (int) $entry->change_amount : (int) $entry->change_amount ); ?>
                                </span>
                                <span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $entry->entry_type ) ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p>No Lesson Credit activity yet.</p>
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

        return $query->posts;
    }

    public static function move_dashboard_card_script() {
        if ( ! self::$rendered ) return;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var card = document.getElementById('lcw-dashboard-card');
            var inner = document.querySelector('.tutor-dashboard-content-inner');
            if (!card || !inner || card.dataset.lcwMoved === '1') return;
            inner.parentNode.insertBefore(card, inner);
            card.dataset.lcwMoved = '1';
        });
        </script>
        <?php
    }
}
