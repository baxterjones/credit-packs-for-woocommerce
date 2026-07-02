<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class LCW_Ledger {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'lcw_credit_ledger';
    }

    public static function get_balance( $user_id ) {
        return (int) get_user_meta( $user_id, 'lcw_credit_balance', true );
    }

    public static function update_balance( $user_id, $balance ) {
        $balance = max( 0, (int) $balance );
        update_user_meta( $user_id, 'lcw_credit_balance', $balance );
        return $balance;
    }

    public static function add_entry( $args ) {
        global $wpdb;

        $defaults = array(
            'user_id'        => 0,
            'order_id'       => null,
            'product_id'     => null,
            'change_amount'  => 0,
            'entry_type'     => 'manual',
            'expiry_date'    => null,
            'note'           => '',
            'created_by'     => get_current_user_id() ?: null,
        );

        $args = wp_parse_args( $args, $defaults );
        $user_id = absint( $args['user_id'] );

        if ( ! $user_id ) {
            return new WP_Error( 'lcw_missing_user', 'Missing user ID.' );
        }

        $current_balance = self::get_balance( $user_id );
        $new_balance = self::update_balance( $user_id, $current_balance + (int) $args['change_amount'] );

        $wpdb->insert(
            self::table_name(),
            array(
                'user_id'       => $user_id,
                'order_id'      => $args['order_id'] ? absint( $args['order_id'] ) : null,
                'product_id'    => $args['product_id'] ? absint( $args['product_id'] ) : null,
                'change_amount' => (int) $args['change_amount'],
                'balance_after' => $new_balance,
                'entry_type'    => sanitize_key( $args['entry_type'] ),
                'expiry_date'   => $args['expiry_date'] ? sanitize_text_field( $args['expiry_date'] ) : null,
                'note'          => sanitize_textarea_field( $args['note'] ),
                'created_by'    => $args['created_by'] ? absint( $args['created_by'] ) : null,
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
        );

        return $wpdb->insert_id;
    }

    public static function get_recent_entries( $limit = 50, $user_id = 0 ) {
        global $wpdb;
        $table = self::table_name();
        $limit = max( 1, min( 200, absint( $limit ) ) );

        if ( $user_id ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
                absint( $user_id ),
                $limit
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
            $limit
        ) );
    }

    public static function get_users_with_balances( $limit = 100 ) {
        global $wpdb;
        $limit = max( 1, min( 300, absint( $limit ) ) );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, CAST(um.meta_value AS SIGNED) AS balance
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
             WHERE um.meta_key = %s AND CAST(um.meta_value AS SIGNED) > 0
             ORDER BY balance DESC
             LIMIT %d",
            'lcw_credit_balance',
            $limit
        ) );
    }

    public static function get_next_expiry( $user_id ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT expiry_date FROM {$table}
             WHERE user_id = %d
             AND change_amount > 0
             AND expiry_date IS NOT NULL
             AND expiry_date >= CURDATE()
             ORDER BY expiry_date ASC
             LIMIT 1",
            absint( $user_id )
        ) );
    }
}
