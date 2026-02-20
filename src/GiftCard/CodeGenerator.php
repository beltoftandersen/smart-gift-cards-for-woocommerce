<?php

namespace GiftCards\GiftCard;

use GiftCards\Support\Options;

defined( 'ABSPATH' ) || exit;

class CodeGenerator {

	/**
	 * Unambiguous character set (no O/0/I/1/l).
	 */
	const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/**
	 * Generate a unique gift card code.
	 *
	 * Format: PREFIX-XXXX-XXXX-XXXX (with extra segment as fallback).
	 *
	 * @return string
	 */
	public static function generate() {
		$prefix = strtoupper( trim( Options::get( 'code_prefix' ) ) );
		if ( empty( $prefix ) ) {
			$prefix = 'GIFT';
		}

		$max_attempts = 10;
		for ( $i = 0; $i < $max_attempts; $i++ ) {
			$code = self::build_code( $prefix, 3 );
			if ( ! Repository::find_by_code( $code ) ) {
				return $code;
			}
		}

		// Fallback: add extra segment and verify uniqueness.
		for ( $i = 0; $i < $max_attempts; $i++ ) {
			$code = self::build_code( $prefix, 4 );
			if ( ! Repository::find_by_code( $code ) ) {
				return $code;
			}
		}

		// Last resort: 5-segment code (virtually impossible collision).
		return self::build_code( $prefix, 5 );
	}

	/**
	 * Build a code with a given number of segments.
	 *
	 * @param string $prefix        Code prefix.
	 * @param int    $segment_count Number of 4-character segments.
	 * @return string
	 */
	private static function build_code( $prefix, $segment_count ) {
		$segments = [];
		for ( $i = 0; $i < $segment_count; $i++ ) {
			$segments[] = self::random_segment( 4 );
		}
		return $prefix . '-' . implode( '-', $segments );
	}

	/**
	 * Generate a random segment of given length.
	 *
	 * @param int $length Segment length.
	 * @return string
	 */
	private static function random_segment( $length ) {
		$charset_len = strlen( self::CHARSET );
		$segment     = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$segment .= self::CHARSET[ random_int( 0, $charset_len - 1 ) ];
		}
		return $segment;
	}
}
