<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter

class BXTR_CP_Ledger {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'bxtr_cp_credit_ledger';
    }

    public static function get_balance( $user_id ) {
        return (int) get_user_meta( $user_id, 'bxtr_cp_credit_balance', true );
    }

    public static function update_balance( $user_id, $balance ) {
        $balance = max( 0, (int) $balance );
        update_user_meta( $user_id, 'bxtr_cp_credit_balance', $balance );
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
            return new WP_Error( 'bxtr_cp_missing_user', 'Missing user ID.' );
        }

        $current_balance = self::get_balance( $user_id );
        $new_balance = self::update_balance( $user_id, $current_balance + (int) $args['change_amount'] );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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
        $table = esc_sql( self::table_name() );
        $limit = max( 1, min( 200, absint( $limit ) ) );

        if ( $user_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
                absint( $user_id ),
                $limit
            ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
            $limit
        ) );
    }

    public static function get_users_with_balances( $limit = 100 ) {
        global $wpdb;
        $limit = max( 1, min( 300, absint( $limit ) ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email, CAST(um.meta_value AS SIGNED) AS balance
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
             WHERE um.meta_key = %s AND CAST(um.meta_value AS SIGNED) > 0
             ORDER BY balance DESC
             LIMIT %d",
            'bxtr_cp_credit_balance',
            $limit
        ) );
    }



    public static function resolve_user_search( $search ) {
        $search = trim( sanitize_text_field( (string) $search ) );
        if ( '' === $search ) {
            return 0;
        }

        if ( ctype_digit( $search ) ) {
            return absint( $search );
        }

        $users = get_users( array(
            'search'         => '*' . $search . '*',
            'search_columns' => array( 'user_login', 'user_nicename', 'user_email', 'display_name' ),
            'number'         => 1,
            'fields'         => array( 'ID' ),
        ) );

        return ! empty( $users[0]->ID ) ? absint( $users[0]->ID ) : 0;
    }

    public static function entry_types() {
        return array( 'grant', 'redeem', 'refund', 'grant_reversal', 'refund_review', 'manual' );
    }

    public static function search_entries( $args = array() ) {
        global $wpdb;

        $args = wp_parse_args( $args, array(
            'limit'   => 100,
            'user_id' => 0,
            'type'    => '',
            'search'  => '',
            'user_search' => '',
        ) );

        $table  = esc_sql( self::table_name() );
        $limit  = max( 1, min( 300, absint( $args['limit'] ) ) );
        $where  = array( '1=1' );
        $params = array();

        if ( ! empty( $args['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $params[] = absint( $args['user_id'] );
        } elseif ( ! empty( $args['user_search'] ) ) {
            $resolved_user = self::resolve_user_search( $args['user_search'] );
            if ( $resolved_user ) {
                $where[]  = 'user_id = %d';
                $params[] = $resolved_user;
            }
        }

        if ( ! empty( $args['type'] ) && in_array( $args['type'], self::entry_types(), true ) ) {
            $where[]  = 'entry_type = %s';
            $params[] = sanitize_key( $args['type'] );
        }

        if ( ! empty( $args['search'] ) ) {
            $like = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where[]  = '(note LIKE %s OR CAST(order_id AS CHAR) LIKE %s OR CAST(product_id AS CHAR) LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d';
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function get_next_expiry( $user_id ) {
        global $wpdb;
        $table = esc_sql( self::table_name() );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
