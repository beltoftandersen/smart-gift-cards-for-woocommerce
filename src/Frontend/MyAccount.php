<?php

namespace GiftCards\Frontend;

use GiftCards\GiftCard\Repository;
use GiftCards\GiftCard\TransactionRepository;
use GiftCards\Cart\CartHandler;

defined( 'ABSPATH' ) || exit;

class MyAccount {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'add_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'menu_item' ] );
		add_action( 'woocommerce_account_gift-cards_endpoint', [ __CLASS__, 'render' ] );
		add_filter( 'the_title', [ __CLASS__, 'endpoint_title' ], 10, 2 );
	}

	/**
	 * Register the rewrite endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'gift-cards', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add "Gift Cards" to My Account menu after Orders.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function menu_item( $items ) {
		$new_items = [];

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( $key === 'orders' ) {
				$new_items['gift-cards'] = __( 'Gift Cards', 'smart-gift-cards-for-woocommerce' );
			}
		}

		return $new_items;
	}

	/**
	 * Set page title for the endpoint.
	 *
	 * @param string $title Page title.
	 * @param int    $id    Post ID.
	 * @return string
	 */
	public static function endpoint_title( $title, $id = null ) {
		global $wp_query;

		if ( isset( $wp_query->query_vars['gift-cards'] )
			&& ! is_admin()
			&& is_main_query()
			&& in_the_loop()
			&& is_account_page()
		) {
			return __( 'Gift Cards', 'smart-gift-cards-for-woocommerce' );
		}
		return $title;
	}

	/**
	 * Render the Gift Cards page in My Account.
	 */
	public static function render() {
		$user_id = get_current_user_id();
		$email   = wp_get_current_user()->user_email;

		// Get cards purchased by this customer + received by email.
		$purchased = Repository::get_by_customer( $user_id );
		$received  = Repository::get_by_recipient( $email );

		// Merge and deduplicate by ID.
		$all_cards = [];
		foreach ( array_merge( $purchased, $received ) as $gc ) {
			$all_cards[ $gc->id ] = $gc;
		}
		$all_cards = array_values( $all_cards );

		// Sort by created_at DESC.
		usort( $all_cards, function ( $a, $b ) {
			return strtotime( $b->created_at ) - strtotime( $a->created_at );
		} );
		?>

		<div class="wcgc-myaccount">
			<?php if ( ! empty( $all_cards ) ) : ?>
				<table class="woocommerce-orders-table wcgc-cards-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Code', 'smart-gift-cards-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Initial Amount', 'smart-gift-cards-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Balance', 'smart-gift-cards-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'smart-gift-cards-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'smart-gift-cards-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_cards as $gc ) : ?>
							<tr class="wcgc-card-row" data-card-id="<?php echo esc_attr( $gc->id ); ?>">
								<td>
									<code><?php echo esc_html( CartHandler::mask_code( $gc->code ) ); ?></code>
									<button type="button" class="wcgc-toggle-transactions" title="<?php esc_attr_e( 'Show transactions', 'smart-gift-cards-for-woocommerce' ); ?>">&#9660;</button>
								</td>
								<td><?php echo wp_kses_post( wc_price( $gc->initial_amount, [ 'currency' => $gc->currency ] ) ); ?></td>
								<td><strong><?php echo wp_kses_post( wc_price( $gc->balance, [ 'currency' => $gc->currency ] ) ); ?></strong></td>
								<td>
									<span class="wcgc-status wcgc-status--<?php echo esc_attr( $gc->status ); ?>">
										<?php echo esc_html( ucfirst( $gc->status ) ); ?>
									</span>
								</td>
								<td>
									<?php
									if ( empty( $gc->expires_at ) ) {
										esc_html_e( 'Never', 'smart-gift-cards-for-woocommerce' );
									} else {
										echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $gc->expires_at ) ) );
									}
									?>
								</td>
							</tr>
							<tr class="wcgc-transactions-row" style="display: none;" data-card-id="<?php echo esc_attr( $gc->id ); ?>">
								<td colspan="5">
									<?php
									$transactions = TransactionRepository::get_by_gift_card( $gc->id );
									if ( ! empty( $transactions ) ) :
									?>
										<table class="wcgc-transactions-table">
											<thead>
												<tr>
													<th><?php esc_html_e( 'Date', 'smart-gift-cards-for-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Type', 'smart-gift-cards-for-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Amount', 'smart-gift-cards-for-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Balance', 'smart-gift-cards-for-woocommerce' ); ?></th>
													<th><?php esc_html_e( 'Note', 'smart-gift-cards-for-woocommerce' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $transactions as $tx ) : ?>
													<tr>
														<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tx->created_at ) ) ); ?></td>
														<td><span class="wcgc-tx-type wcgc-tx-type--<?php echo esc_attr( $tx->type ); ?>"><?php echo esc_html( ucfirst( $tx->type ) ); ?></span></td>
														<td>
															<?php
															$prefix = $tx->type === 'debit' ? '-' : '+';
															echo esc_html( $prefix ) . wp_kses_post( wc_price( $tx->amount, [ 'currency' => $gc->currency ] ) );
															?>
														</td>
														<td><?php echo wp_kses_post( wc_price( $tx->balance_after, [ 'currency' => $gc->currency ] ) ); ?></td>
														<td><?php echo esc_html( $tx->note ); ?></td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									<?php else : ?>
										<p><?php esc_html_e( 'No transactions yet.', 'smart-gift-cards-for-woocommerce' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'You don\'t have any gift cards yet.', 'smart-gift-cards-for-woocommerce' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
