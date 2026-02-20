<?php

namespace GiftCards\Support;

defined( 'ABSPATH' ) || exit;

class Options {

	const OPTION = 'wcgc_options';

	/** @var array|null Static cache for the current request. */
	private static $cache = null;

	/**
	 * Full defaults array for every setting.
	 */
	public static function defaults() {
		return [
			'code_prefix'              => 'GIFT',
			'allow_custom_amount'      => '1',
			'min_custom_amount'        => '5',
			'max_custom_amount'        => '500',
			'amount_button_focus_color' => '#7f54b3',
			'default_expiry_days'      => '365',
			'show_dedicated_field'     => '0',
			'dedicated_field_placement' => 'auto',
			'product_form_placement'   => 'auto',
			'cleanup_on_uninstall'     => '0',
		];
	}

	/**
	 * Get option value(s). Returns single key or full merged array.
	 */
	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			self::$cache = wp_parse_args(
				get_option( self::OPTION, [] ),
				self::defaults()
			);
		}

		if ( null === $key ) {
			return self::$cache;
		}

		return self::$cache[ $key ] ?? null;
	}

	/**
	 * Invalidate static cache when options are updated externally.
	 */
	public static function invalidate_cache() {
		self::$cache = null;
	}

	/**
	 * Sanitize callback for register_setting.
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : [];
		$out   = [];
		$prev  = self::get();

		$fields = [
			'code_prefix'              => 'text',
			'allow_custom_amount'      => 'bool',
			'min_custom_amount'        => 'int',
			'max_custom_amount'        => 'int',
			'amount_button_focus_color' => 'color',
			'default_expiry_days'      => 'int',
			'show_dedicated_field'     => 'bool',
			'dedicated_field_placement' => 'select',
			'product_form_placement'   => 'select',
			'cleanup_on_uninstall'     => 'bool',
		];

		$selects = [
			'dedicated_field_placement' => [ 'auto', 'shortcode', 'both' ],
			'product_form_placement'   => [ 'auto', 'shortcode' ],
		];

		foreach ( $fields as $field => $type ) {
			$has = array_key_exists( $field, $input );
			$val = $has ? $input[ $field ] : '';

			switch ( $type ) {
				case 'bool':
					$out[ $field ] = ( $has && in_array( $val, [ '1', 'on', 'yes', true ], true ) ) ? '1' : '0';
					break;

				case 'int':
					$out[ $field ] = $has && is_numeric( $val ) ? (string) absint( $val ) : $prev[ $field ];
					break;

				case 'select':
					$allowed       = $selects[ $field ] ?? [];
					$out[ $field ] = ( $has && in_array( $val, $allowed, true ) ) ? $val : $prev[ $field ];
					break;

				case 'color':
					$color = $has ? sanitize_hex_color( $val ) : '';
					$out[ $field ] = $color ? $color : ( $prev[ $field ] ?? self::defaults()[ $field ] );
					break;

				default:
					$out[ $field ] = sanitize_text_field( $val );
			}
		}

		// Reset cache so next get() picks up new values.
		self::$cache = null;

		return $out;
	}
}
