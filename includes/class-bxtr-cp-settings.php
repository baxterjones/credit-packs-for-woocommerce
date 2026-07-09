<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BXTR_CP_Settings {
    const OPTION = 'bxtr_cp_settings';

    public static function defaults() {
        return array(
            'credit_label_singular' => 'Credit',
            'credit_label_plural'   => 'Credits',
            'pack_label'            => 'Credit Pack',
            'style_preset'          => 'gold',
            'accent_colour'         => '#b98b35',
            'background_colour'     => '#fff9ed',
            'border_colour'         => '#e2c88f',
            'text_colour'           => '#4a3314',
            'border_radius'         => '14',
            'font_size'             => '14',
            'label_font_size'       => '14',
            'icon_mode'             => 'builtin',
            'icon_builtin'          => 'ticket',
            'icon_class'            => '',
            'redeemable_title'      => 'Available with {pack_label_plural}',
            'redeemable_message'    => 'Use {credits_required} {credit_label} from your balance, or checkout normally.',
            'balance_message'       => 'You currently have {credit_balance} {credit_label_plural} available.',
            'checkbox_label'        => 'Use my {credit_label_plural} for this purchase',
            'pack_title'            => "What's included",
            'pack_message'          => 'Includes {credits_granted} {credit_label_plural} for eligible products.',
            'pack_redeem_message'   => 'Redeem {credit_label_plural} against any eligible product.',
            'dashboard_title'       => 'Your {pack_label}',
            'dashboard_balance'     => '{credit_balance} {credit_label_plural} available to use.',
            'remove_data_on_uninstall' => 'no',
        );
    }

    public static function get_all() {
        $settings = get_option( self::OPTION, array() );
        return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
    }

    public static function get( $key ) {
        $settings = self::get_all();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
    }

    public static function credit_label( $amount = 2 ) {
        return 1 === absint( $amount ) ? self::get( 'credit_label_singular' ) : self::get( 'credit_label_plural' );
    }

    public static function save_from_post( $posted ) {
        $defaults = self::defaults();
        $current  = self::get_all();
        $clean    = $current;

        foreach ( $posted as $key => $raw_value ) {
            if ( ! array_key_exists( $key, $defaults ) ) {
                continue;
            }

            $value   = wp_unslash( $raw_value );
            $default = $defaults[ $key ];

            if ( in_array( $key, array( 'accent_colour', 'background_colour', 'border_colour', 'text_colour' ), true ) ) {
                $value = trim( (string) $value );
                if ( '' === $value ) {
                    $clean[ $key ] = '';
                    continue;
                }

                $sanitized_colour = sanitize_hex_color( $value );
                $clean[ $key ] = $sanitized_colour ? $sanitized_colour : ( isset( $current[ $key ] ) ? $current[ $key ] : $default );
                continue;
            }

            if ( 'border_radius' === $key ) {
                $clean[ $key ] = (string) max( 0, min( 40, absint( $value ) ) );
                continue;
            }

            if ( in_array( $key, array( 'font_size', 'label_font_size' ), true ) ) {
                $clean[ $key ] = (string) max( 11, min( 22, absint( $value ) ) );
                continue;
            }

            if ( 'style_preset' === $key ) {
                $clean[ $key ] = in_array( $value, array( 'gold', 'silver', 'bronze', 'minimal', 'custom' ), true ) ? $value : 'gold';
                continue;
            }

            if ( 'icon_mode' === $key ) {
                $clean[ $key ] = in_array( $value, array( 'builtin', 'class', 'none' ), true ) ? $value : 'builtin';
                continue;
            }

            if ( 'icon_builtin' === $key ) {
                $clean[ $key ] = in_array( $value, array( 'ticket', 'check', 'card', 'package' ), true ) ? $value : 'ticket';
                continue;
            }

            if ( 'icon_class' === $key ) {
                $clean[ $key ] = trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', sanitize_text_field( $value ) ) );
                continue;
            }

            if ( 'remove_data_on_uninstall' === $key ) {
                $clean[ $key ] = 'yes' === $value ? 'yes' : 'no';
                continue;
            }

            $clean[ $key ] = sanitize_text_field( $value );
        }

        update_option( self::OPTION, wp_parse_args( $clean, $defaults ) );
    }

    public static function replace_tokens( $template, $data = array() ) {
        $data = wp_parse_args( $data, array(
            'credit_balance'     => 0,
            'credits_required'   => 0,
            'credits_granted'    => 0,
            'product_name'       => '',
            'credit_label'       => self::get( 'credit_label_singular' ),
            'credit_label_plural'=> self::get( 'credit_label_plural' ),
            'pack_label'         => self::get( 'pack_label' ),
            'pack_label_plural'  => self::get( 'pack_label' ) . 's',
        ) );

        foreach ( $data as $key => $value ) {
            $template = str_replace( '{' . $key . '}', (string) $value, $template );
        }

        return $template;
    }

    public static function frontend_style_attr() {
        $s = self::get_all();
        return sprintf(
            '--bxtr-cp-accent:%1$s;--bxtr-cp-bg:%2$s;--bxtr-cp-border:%3$s;--bxtr-cp-text:%4$s;--bxtr-cp-radius:%5$dpx;--bxtr-cp-font-size:%6$dpx;--bxtr-cp-label-font-size:%7$dpx;',
            esc_attr( $s['accent_colour'] ),
            esc_attr( $s['background_colour'] ),
            esc_attr( $s['border_colour'] ),
            esc_attr( $s['text_colour'] ),
            absint( $s['border_radius'] ),
            absint( $s['font_size'] ),
            absint( $s['label_font_size'] )
        );
    }


    public static function maybe_upgrade_defaults() {
        $settings = get_option( self::OPTION, array() );
        if ( ! is_array( $settings ) ) {
            return;
        }

        $changed = false;
        $replacements = array(
            'checkbox_label'      => array(
                'Use my {credit_label_plural} for this booking'  => 'Use my {credit_label_plural} for this purchase',
                'Use my {credit_label_plural} for this product'  => 'Use my {credit_label_plural} for this purchase',
            ),
            'pack_redeem_message' => array(
                'Redeem {credit_label_plural} against any eligible booking.' => 'Redeem {credit_label_plural} against any eligible product.',
            ),
        );

        foreach ( $replacements as $key => $map ) {
            if ( empty( $settings[ $key ] ) ) {
                continue;
            }
            foreach ( $map as $old => $new ) {
                if ( $settings[ $key ] === $old ) {
                    $settings[ $key ] = $new;
                    $changed = true;
                }
            }
        }

        if ( $changed ) {
            update_option( self::OPTION, wp_parse_args( $settings, self::defaults() ) );
        }
    }

    public static function icon_html() {
        $mode = self::get( 'icon_mode' );
        if ( 'none' === $mode ) return '';

        if ( 'class' === $mode ) {
            $class = self::get( 'icon_class' );
            return $class ? '<span class="bxtr-cp-icon" aria-hidden="true"><span class="' . esc_attr( $class ) . '" aria-hidden="true"></span></span>' : '';
        }

        $icon = self::get( 'icon_builtin' );
        $paths = array(
            'ticket'  => '<path d="M5 7h14v3a2 2 0 0 0 0 4v3H5v-3a2 2 0 0 0 0-4V7z"/><path d="M9 9v6"/>',
            'check'   => '<circle cx="12" cy="12" r="8"/><path d="M8.5 12.5l2.2 2.2 4.8-5"/>',
            'card'    => '<rect x="5" y="7" width="14" height="10" rx="2"/><path d="M5 10h14"/><path d="M8 14h4"/>',
            'package' => '<path d="M12 3l8 4-8 4-8-4 8-4z"/><path d="M4 7v9l8 5 8-5V7"/><path d="M12 11v10"/>',
        );

        $path = isset( $paths[ $icon ] ) ? $paths[ $icon ] : $paths['ticket'];
        return '<span class="bxtr-cp-icon bxtr-cp-icon--svg" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false">' . $path . '</svg></span>';
    }

    public static function icon_kses_allowed_html() {
        return array(
            'span' => array(
                'class'       => true,
                'aria-hidden' => true,
            ),
            'svg'  => array(
                'viewbox'    => true,
                'viewBox'    => true,
                'focusable'  => true,
                'aria-hidden'=> true,
            ),
            'path' => array(
                'd' => true,
            ),
            'circle' => array(
                'cx' => true,
                'cy' => true,
                'r'  => true,
            ),
            'rect' => array(
                'x'      => true,
                'y'      => true,
                'width'  => true,
                'height' => true,
                'rx'     => true,
            ),
        );
    }

    public static function safe_icon_html() {
        return wp_kses( self::icon_html(), self::icon_kses_allowed_html() );
    }

}
