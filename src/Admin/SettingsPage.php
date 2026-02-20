<?php

namespace GiftCards\Admin;

use GiftCards\Support\Options;
use GiftCards\GiftCard\Repository;
use GiftCards\GiftCard\GiftCardCreator;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	const GROUP = 'wcgc_settings_group';
	const SLUG  = 'wcgc-gift-cards';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_manual_create' ] );
	}

	/**
	 * Add submenu under WooCommerce.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Gift Cards', 'smart-gift-cards-for-woocommerce' ),
			__( 'Gift Cards', 'smart-gift-cards-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting( self::GROUP, Options::OPTION, [
			'type'              => 'array',
			'sanitize_callback' => [ Options::class, 'sanitize' ],
			'default'           => Options::defaults(),
			'show_in_rest'      => false,
		] );

		$settings_slug = self::SLUG . '-settings';

		// ── Code Section ──
		add_settings_section( 'wcgc_code', __( 'Gift Card Codes', 'smart-gift-cards-for-woocommerce' ), '__return_null', $settings_slug );
		self::add_text( 'code_prefix', __( 'Code Prefix', 'smart-gift-cards-for-woocommerce' ), 'wcgc_code', $settings_slug,
			__( 'Prefix for generated codes (e.g., GIFT → GIFT-XXXX-XXXX-XXXX).', 'smart-gift-cards-for-woocommerce' ) );

		// ── Amounts Section ──
		add_settings_section( 'wcgc_amounts', __( 'Amounts', 'smart-gift-cards-for-woocommerce' ), '__return_null', $settings_slug );
		self::add_checkbox( 'allow_custom_amount', __( 'Allow Custom Amount', 'smart-gift-cards-for-woocommerce' ), 'wcgc_amounts', $settings_slug );
		self::add_number( 'min_custom_amount', __( 'Minimum Custom Amount', 'smart-gift-cards-for-woocommerce' ), 'wcgc_amounts', '0.01', '0', '', $settings_slug );
		self::add_number( 'max_custom_amount', __( 'Maximum Custom Amount', 'smart-gift-cards-for-woocommerce' ), 'wcgc_amounts', '0.01', '0', '', $settings_slug );

		// ── Expiry Section ──
		add_settings_section( 'wcgc_expiry', __( 'Expiry', 'smart-gift-cards-for-woocommerce' ), '__return_null', $settings_slug );
		self::add_number( 'default_expiry_days', __( 'Default Expiry (days)', 'smart-gift-cards-for-woocommerce' ), 'wcgc_expiry', '1', '0',
			__( 'Set to 0 for no expiry.', 'smart-gift-cards-for-woocommerce' ), $settings_slug );

		// ── Dedicated Field Section ──
		add_settings_section( 'wcgc_field', __( 'Dedicated Gift Card Field', 'smart-gift-cards-for-woocommerce' ), function () {
			echo '<p>' . esc_html__( 'Gift cards always work in the standard WooCommerce coupon field. Optionally show a separate "Apply Gift Card" field.', 'smart-gift-cards-for-woocommerce' ) . '</p>';
		}, $settings_slug );
		self::add_checkbox( 'show_dedicated_field', __( 'Show Dedicated Field', 'smart-gift-cards-for-woocommerce' ), 'wcgc_field', $settings_slug );
		self::add_select( 'dedicated_field_placement', __( 'Field Placement', 'smart-gift-cards-for-woocommerce' ), 'wcgc_field', [
			'auto'      => __( 'Automatic (cart & checkout)', 'smart-gift-cards-for-woocommerce' ),
			'shortcode' => __( 'Shortcode only', 'smart-gift-cards-for-woocommerce' ),
		], $settings_slug );

		add_settings_field( 'wcgc_shortcodes_info', __( 'Shortcode', 'smart-gift-cards-for-woocommerce' ), function () {
			echo '<p><code style="cursor:pointer;user-select:all;">[wcgc_apply_field]</code> &mdash; '
				. esc_html__( 'Apply Gift Card field (cart & checkout)', 'smart-gift-cards-for-woocommerce' ) . '</p>';
		}, $settings_slug, 'wcgc_field' );

		// ── Product Page Section ──
		add_settings_section( 'wcgc_product_page', __( 'Product Page', 'smart-gift-cards-for-woocommerce' ), function () {
			echo '<p>' . esc_html__( 'Controls how the gift card amount selector and recipient fields appear on the product page.', 'smart-gift-cards-for-woocommerce' ) . '</p>';
		}, $settings_slug );
		self::add_select( 'product_form_placement', __( 'Product Form Placement', 'smart-gift-cards-for-woocommerce' ), 'wcgc_product_page', [
			'auto'      => __( 'Automatic (WooCommerce hook)', 'smart-gift-cards-for-woocommerce' ),
			'shortcode' => __( 'Shortcode only — [wcgc_product_form]', 'smart-gift-cards-for-woocommerce' ),
		], $settings_slug );
		self::add_color(
			'amount_button_focus_color',
			__( 'Amount Button Focus Color', 'smart-gift-cards-for-woocommerce' ),
			'wcgc_product_page',
			__( 'Color used for the amount button focus indicator on the product page.', 'smart-gift-cards-for-woocommerce' ),
			$settings_slug
		);
		add_settings_field( 'wcgc_product_form_shortcode', __( 'Shortcode', 'smart-gift-cards-for-woocommerce' ), function () {
			echo '<p><code style="cursor:pointer;user-select:all;">[wcgc_product_form]</code> &mdash; '
				. esc_html__( 'Gift card product form for page builders (Bricks, Elementor)', 'smart-gift-cards-for-woocommerce' ) . '</p>';
		}, $settings_slug, 'wcgc_product_page' );

		// ── Integrations Section (only when Loyalty Rewards is active) ──
		if ( class_exists( 'LoyaltyRewards\\Plugin' ) ) {
			add_settings_section( 'wcgc_integrations', __( 'Integrations', 'smart-gift-cards-for-woocommerce' ), function () {
				echo '<p>' . esc_html__( 'Settings for third-party plugin compatibility.', 'smart-gift-cards-for-woocommerce' ) . '</p>';
			}, $settings_slug );
			self::add_checkbox(
				'allow_points_for_gift_cards',
				__( 'Allow Loyalty Points for Gift Cards', 'smart-gift-cards-for-woocommerce' ),
				'wcgc_integrations',
				$settings_slug,
				__( 'Allow customers to use loyalty points to pay for gift card purchases.', 'smart-gift-cards-for-woocommerce' )
			);
		}

		// ── Advanced Section ──
		add_settings_section( 'wcgc_advanced', __( 'Advanced', 'smart-gift-cards-for-woocommerce' ), '__return_null', $settings_slug );
		self::add_checkbox( 'cleanup_on_uninstall', __( 'Delete All Data on Uninstall', 'smart-gift-cards-for-woocommerce' ), 'wcgc_advanced', $settings_slug );
	}

	/**
	 * Get the current active tab.
	 */
	private static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab display.
		return isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
	}

	/**
	 * Render the tabbed page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$current_tab = self::current_tab();
		$tabs = [
			'dashboard'  => __( 'Dashboard', 'smart-gift-cards-for-woocommerce' ),
			'gift-cards' => __( 'Gift Cards', 'smart-gift-cards-for-woocommerce' ),
			'settings'   => __( 'Settings', 'smart-gift-cards-for-woocommerce' ),
		];

		/**
		 * Filter the admin page tabs.
		 *
		 * @param array $tabs Associative array of tab slug => label.
		 */
		$tabs = apply_filters( 'wcgc_admin_tabs', $tabs );
		?>
		<div class="wrap wcgc-settings-wrap">
			<h1><?php esc_html_e( 'Gift Cards', 'smart-gift-cards-for-woocommerce' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $slug ) ); ?>"
					   class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $current_tab ) {
				case 'gift-cards':
					self::render_gift_cards_tab();
					break;
				case 'settings':
					self::render_settings_tab();
					break;
				case 'dashboard':
					self::render_dashboard_tab();
					break;
				default:
					/**
					 * Fires to render a custom admin tab's content.
					 *
					 * @param string $current_tab The active tab slug.
					 */
					do_action( 'wcgc_admin_tab_' . $current_tab );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Dashboard tab.
	 */
	private static function render_dashboard_tab() {
		$stats = Repository::get_summary_stats();
		?>
		<div class="wcgc-dashboard" style="margin-top: 16px;">
			<div class="wcgc-stats-cards" style="display: flex; gap: 16px; margin: 16px 0; flex-wrap: wrap;">
				<?php
				$cards = [
					__( 'Total Issued', 'smart-gift-cards-for-woocommerce' )        => number_format_i18n( $stats['total_issued'] ),
					__( 'Total Redeemed', 'smart-gift-cards-for-woocommerce' )      => number_format_i18n( $stats['total_redeemed'] ),
					__( 'Outstanding Balance', 'smart-gift-cards-for-woocommerce' ) => wp_strip_all_tags( wc_price( $stats['outstanding_balance'] ) ),
					__( 'Expired', 'smart-gift-cards-for-woocommerce' )             => number_format_i18n( $stats['total_expired'] ),
				];
				foreach ( $cards as $label => $value ) :
				?>
					<div class="wcgc-stat-card">
						<div class="wcgc-stat-label"><?php echo esc_html( $label ); ?></div>
						<div class="wcgc-stat-value"><?php echo esc_html( $value ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php
			/**
			 * Fires after the stats cards on the dashboard tab.
			 */
			do_action( 'wcgc_dashboard_after_stats' );
			?>
		</div>
		<?php
	}

	/**
	 * Gift Cards list tab.
	 */
	private static function render_gift_cards_tab() {
		$list_table = new GiftCardListTable();
		$list_table->prepare_items();
		?>
		<div class="wcgc-gift-cards-wrap" style="margin-top: 16px;">
			<button type="button" class="button button-primary wcgc-toggle-add-form">
				<?php esc_html_e( 'Add Gift Card', 'smart-gift-cards-for-woocommerce' ); ?>
			</button>

			<div class="wcgc-add-form" style="display: none; margin: 16px 0; padding: 16px; background: #fff; border: 1px solid #c3c4c7;">
				<h3><?php esc_html_e( 'Create Gift Card Manually', 'smart-gift-cards-for-woocommerce' ); ?></h3>
				<form method="post" action="">
					<?php wp_nonce_field( 'wcgc_manual_create', 'wcgc_create_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="wcgc_amount"><?php esc_html_e( 'Amount', 'smart-gift-cards-for-woocommerce' ); ?></label></th>
							<td><input type="number" name="wcgc_amount" id="wcgc_amount" step="0.01" min="0.01" class="small-text" required /></td>
						</tr>
						<tr>
							<th><label for="wcgc_recipient_name"><?php esc_html_e( 'Recipient Name', 'smart-gift-cards-for-woocommerce' ); ?></label></th>
							<td><input type="text" name="wcgc_recipient_name" id="wcgc_recipient_name" class="regular-text" /></td>
						</tr>
						<tr>
							<th><label for="wcgc_recipient_email"><?php esc_html_e( 'Recipient Email', 'smart-gift-cards-for-woocommerce' ); ?></label></th>
							<td><input type="email" name="wcgc_recipient_email" id="wcgc_recipient_email" class="regular-text" /></td>
						</tr>
						<tr>
							<th><label for="wcgc_message"><?php esc_html_e( 'Message', 'smart-gift-cards-for-woocommerce' ); ?></label></th>
							<td><textarea name="wcgc_message" id="wcgc_message" rows="3" class="large-text"></textarea></td>
						</tr>
					</table>
					<?php submit_button( __( 'Create Gift Card', 'smart-gift-cards-for-woocommerce' ), 'primary', 'wcgc_create_submit' ); ?>
				</form>
			</div>

			<form method="post">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<input type="hidden" name="tab" value="gift-cards" />
				<?php
				$list_table->search_box( __( 'Search', 'smart-gift-cards-for-woocommerce' ), 'wcgc_search' );
				$list_table->display();
				?>
			</form>

			<?php
			/**
			 * Fires after the gift cards list table.
			 */
			do_action( 'wcgc_admin_after_gift_cards_list' );
			?>
		</div>
		<?php
	}

	/**
	 * Settings tab.
	 */
	private static function render_settings_tab() {
		settings_errors();
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( self::GROUP );
			do_settings_sections( self::SLUG . '-settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Handle manual gift card creation.
	 */
	public static function handle_manual_create() {
		if ( ! isset( $_POST['wcgc_create_submit'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'wcgc_manual_create', 'wcgc_create_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$gc_id = GiftCardCreator::create_manual( [
			'amount'          => isset( $_POST['wcgc_amount'] ) ? (float) $_POST['wcgc_amount'] : 0,
			'recipient_name'  => isset( $_POST['wcgc_recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wcgc_recipient_name'] ) ) : '',
			'recipient_email' => isset( $_POST['wcgc_recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['wcgc_recipient_email'] ) ) : '',
			'message'         => isset( $_POST['wcgc_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcgc_message'] ) ) : '',
		] );

		if ( $gc_id ) {
			add_settings_error( 'wcgc_messages', 'wcgc_created', __( 'Gift card created successfully!', 'smart-gift-cards-for-woocommerce' ), 'success' );
		} else {
			add_settings_error( 'wcgc_messages', 'wcgc_error', __( 'Failed to create gift card. Please check the amount.', 'smart-gift-cards-for-woocommerce' ), 'error' );
		}
	}

	/* ── Field Helpers ─────────────────────────────────────────── */

	private static function add_checkbox( $key, $label, $section, $page, $desc = '' ) {
		add_settings_field( "wcgc_{$key}", $label, function () use ( $key, $desc ) {
			$val = Options::get( $key );
			printf(
				'<input type="checkbox" name="%s[%s]" value="1" %s />',
				esc_attr( Options::OPTION ),
				esc_attr( $key ),
				checked( $val, '1', false )
			);
			if ( $desc ) {
				printf( '<p class="description">%s</p>', wp_kses( $desc, [ 'code' => [], 'br' => [] ] ) );
			}
		}, $page, $section );
	}

	private static function add_number( $key, $label, $section, $step, $min, $desc, $page ) {
		add_settings_field( "wcgc_{$key}", $label, function () use ( $key, $step, $min, $desc ) {
			$val = Options::get( $key );
			printf(
				'<input type="number" name="%s[%s]" value="%s" step="%s" min="%s" class="small-text" />',
				esc_attr( Options::OPTION ),
				esc_attr( $key ),
				esc_attr( $val ),
				esc_attr( $step ),
				esc_attr( $min )
			);
			if ( $desc ) {
				printf( '<p class="description">%s</p>', esc_html( $desc ) );
			}
		}, $page, $section );
	}

	private static function add_text( $key, $label, $section, $page, $desc = '' ) {
		add_settings_field( "wcgc_{$key}", $label, function () use ( $key, $desc ) {
			$val = Options::get( $key );
			printf(
				'<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
				esc_attr( Options::OPTION ),
				esc_attr( $key ),
				esc_attr( $val )
			);
			if ( $desc ) {
				printf( '<p class="description">%s</p>', esc_html( $desc ) );
			}
		}, $page, $section );
	}

	private static function add_color( $key, $label, $section, $desc, $page ) {
		add_settings_field( "wcgc_{$key}", $label, function () use ( $key, $desc ) {
			$val = sanitize_hex_color( Options::get( $key ) );
			if ( ! $val ) {
				$val = '#7f54b3';
			}

			$base_id = 'wcgc_' . sanitize_key( $key );
			echo '<div class="wcgc-color-setting">';
			printf(
				'<input type="color" id="%1$s_picker" value="%2$s" class="wcgc-color-picker" data-target="%1$s_hex" />',
				esc_attr( $base_id ),
				esc_attr( $val )
			);

			printf(
				'<input type="text" id="%1$s_hex" name="%2$s[%3$s]" value="%4$s" class="regular-text wcgc-color-hex" placeholder="#7f54b3" maxlength="7" />',
				esc_attr( $base_id ),
				esc_attr( Options::OPTION ),
				esc_attr( $key ),
				esc_attr( $val )
			);
			echo '</div>';

			if ( $desc ) {
				printf( '<p class="description">%s</p>', esc_html( $desc ) );
			}
		}, $page, $section );
	}

	private static function add_select( $key, $label, $section, $choices, $page ) {
		add_settings_field( "wcgc_{$key}", $label, function () use ( $key, $choices ) {
			$val = Options::get( $key );
			printf( '<select name="%s[%s]">', esc_attr( Options::OPTION ), esc_attr( $key ) );
			foreach ( $choices as $value => $text ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $value ),
					selected( $val, $value, false ),
					esc_html( $text )
				);
			}
			echo '</select>';
		}, $page, $section );
	}
}
